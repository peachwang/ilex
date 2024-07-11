<?php

namespace Ilex\Base\Controller\Service;

use \Exception;
use \ReflectionClass;
use \ReflectionMethod;
use \Ilex\Core\Context;
use \Ilex\Core\Debug;
use \Ilex\Core\Loader;
use \Ilex\Lib\Http;
use \Ilex\Lib\Kit;
use \Ilex\Lib\UserException;
use \Ilex\Base\Controller\BaseController;
use \Ilex\Base\Model\Core\BaseCore;
use \Ilex\Base\Model\Core\Queue\QueueCore;
use \Ilex\Lib\MongoDB\MongoDBCollection;

/**
 * Class BaseService
 * Base class of service controllers.
 * @package Ilex\Base\Controller\Service
 *
 * @method final public __call(string $method_name, array $arg_list)
 * 
 * @method final private array prepareExecutionRecord(string $method_name)
 * @method final private       fail(Exception $exception)
 * @method final private       succeed(mixed $computation_data, mixed $operation_status
 *                                 , boolean $close_cgi_only = FALSE)
 * @method final private       respond(array $result, int $status_code
 *                                 , boolean $close_cgi_only = FALSE)
 */
abstract class BaseService extends BaseController
{

    private $result   = [
        'code'          => NULL,
        'database'      => [ ],
        'mainException' => NULL,
        'monitor'       => NULL,
        'data'          => [ ],
        'status'        => [ ],
        'process'       => [ ],
    ];

    private $hasCalledCoreModel = FALSE;
    private $isProcessed        = FALSE;
    private $needLock           = NULL;

    public function __construct()
    {
        Context::trySetCurrentUser();
        $this->loadCore('Queue/Queue');
    }

    final protected function ensureLogin()
    {
        $user_type_list = func_get_args();
        if (TRUE === Kit::in('Administrator', $user_type_list)
            AND 1 === Kit::len($user_type_list)
            AND FALSE === Context::isLogin([ ])) // Administrator专属API 且当前无用户登录 待删除此逻辑
            return; // @TODO: CAUTION
        if (FALSE === Context::isLogin($user_type_list))
            throw new UserException('Login failed.');
        if (0 < Kit::len($user_type_list)) { // 非游客、已登录情形
            $this->QueueCore->push($this->needLock);
            while (TRUE === $this->QueueCore->hasItemsAhead()) {
                Debug::monitor('Waited', [
                    'now'   => Kit::microTimestampAtNow(),
                    'me'    => $this->QueueCore->getPushingTimestamp(),
                    'ahead' => $this->QueueCore->getItemsAhead()->batch('getAbstract'),
                ], TRUE);
                usleep(QueueCore::T_SLEEP); // sleep 0.1 second 
            }
            Context::refresh();
            Debug::resetStartTime();
        }
    }

    final protected function loadInput()
    {
        $handler_name = 'Input';
        return ($this->$handler_name = Loader::loadInput());
    }

    final protected function loadCore($path)
    {
        $handler_name = Loader::getHandlerFromPath($path) . 'Core';
        return ($this->$handler_name = Loader::loadCore($path));
    }

    /**
     * @param string $method_name
     * @param array  $arg_list
     */
    final public function __call($method_name, $arg_list) 
    {
        $execution_record = [];
        $execution_id = Debug::addExecutionRecord($execution_record);
        Debug::pushExecutionId($execution_id);
        try {
            $execution_record     = $this->prepareExecutionRecord($method_name, $arg_list);
            $class_name           = $execution_record['class'];
            $input                = $execution_record['input'];
            $method_accessibility = $execution_record['method_accessibility'];
            $handler_prefix       = $execution_record['handler_prefix'];
            $handler_suffix       = $execution_record['handler_suffix'];
            Debug::updateExecutionRecord($execution_id, $execution_record);

            if (FALSE === $method_accessibility) 
                throw new UserException(
                    "Handler($class_name :: $method_name) is not accessible.", $execution_record);

            // $config_model_name = $this->configModelName;
            // if (TRUE === is_null($config_model_name) OR TRUE === is_null($this->$config_model_name))
                // throw new UserException("Config model($config_model_name) not loaded in $class_name.");
            // Method validateModelPrivilege should throw exception if the validation fails.
            // $execution_record['validateModelPrivilege']
                // = $this->$config_model_name->validateModelPrivilege($handler_suffix, $method_name);

            // $data_model_name = $this->dataModelName;
            // if (TRUE === is_null($data_model_name) OR TRUE === is_null($this->$data_model_name))
                // throw new UserException("Data model($data_model_name) not loaded in $class_name.");
            // Method validateInput should throw exception if the validation fails,
            // and it should load the config model and fetch the config info itself.
            // $input_validation_result
                // = $execution_record['validateInput']
                // = $this->$data_model_name->validateInput($method_name, $input);
            // Now the validation passed.
            
            $execution_record['is_time_consuming'] = $is_time_consuming = $arg_list[0];
            if (TRUE === $is_time_consuming) $this->succeedRequest(NULL, NULL, TRUE);
            
            $execution_record['need_lock'] = $this->needLock = $arg_list[1];

            // Method sanitizeInput should load the config model and fetch the config info itself.
            // $input_sanitization_result // a list
                // = $execution_record['sanitizeInput']
                // = $this->$data_model_name->sanitizeInput(
                    // $method_name, $input, $input_validation_result);
            
            // $this->$method_name($input_sanitization_result);
            $this->$method_name($input);
            $service_result
                = $execution_record['service_result']
                = Kit::extract($this->result, [ 'data', 'status' ]);
            $code = $this->getCode();
            if (TRUE === is_null($code)) $this->fail();
            $code = $this->getCode();
            if (FALSE === Kit::in($code, [ 1, 2 ]))
                throw new UserException('Invalid code after service has finished.', $code);
            if (1 === $code)
                $this->succeedRequest($execution_id, $execution_record); // END NOW
            
            // Now code must be 2.

            // Method validateServiceResult should throw exception if the validation fails,
            // and it should load the config model and fetch the config info itself.
            // @CAUTION 
            // $service_result_validation_result
                // = $execution_record['validateServiceResult']
                // = $this->$data_model_name->validateServiceResult($method_name, $service_result);
            // Now the validation passed.
            
            // Method sanitizeServiceResult should load the config model
            // and fetch the config info itself.
            // $service_result_sanitization_result
                // = $execution_record['sanitizeServiceResult']
                // = $this->$data_model_name->sanitizeServiceResult(
                    // $method_name, $service_result, $service_result_validation_result);
            // $service_result_validation_result should contains
            // and only contains three fields: code, data, status.
            // $this->result['data'] = $computation_data = $service_result_sanitization_result['data'];
            $this->result['data'] = $computation_data = $service_result['data'];
            // $this->result['status'] = $operation_status = $service_result_sanitization_result['status'];
            $this->result['status'] = $operation_status = $service_result['status'];
            
            $this->succeedRequest($execution_id, $execution_record);
        } catch (Exception $e) {
            $this->failRequest(
                $execution_id, $execution_record,
                new UserException('Service execution failed.', $execution_record, $e)
            );
        }
    }

    /**
     * @param string $method_name
     * @return array
     */
    final private function prepareExecutionRecord($method_name)
    {
        $input      = $this->loadInput()->cleanInput();
        $class_name = get_called_class();
        $execution_record = $this->generateExecutionRecord($class_name, $method_name) + [
            'input' => $input,
        ];
        return $execution_record;
    }

    final protected function succeed()
    {
        if (FALSE === $this->hasCalledCoreModel)
            throw new UserException('Succeed before calling any core model.');
        $this->setCode(2);
    }

    // @CAUTION can not be invoked from XService
    final private function fail()
    {
        $this->setCode(1);
    }

    final private function setCode($code)
    {
        $current_code = $this->result['code'];
        if (FALSE === Kit::in($code, [ 0, 1, 2, 3 ]))
            throw new UserException('Invalid $code.', $code);
        if (FALSE === is_null($current_code) 
            AND FALSE === Kit::in($current_code, [ 0, 1, 2, 3 ]))
            throw new UserException('Invalid $current_code.', $current_code);
        if (0 === $current_code)
            throw new UserException("Can not change code(${current_code}) to $code after the request has finished.", $current_code);
        if ((TRUE === is_null($current_code) AND TRUE === Kit::in($code, [ 0, 1, 2, 3 ]))
            OR (1 === $current_code AND TRUE === Kit::in($code, [ 0, 1 ]))
            OR (2 === $current_code AND TRUE === Kit::in($code, [ 0, 1, 2 ]))
            OR (3 === $current_code AND TRUE === Kit::in($code, [ 0, 1, 2 ]))
        ) {
            $this->result['code'] = $code;
            return $code;
        } elseif (1 === $current_code AND 2 === $code) {
            // ignore in case
        } else throw new UserException("Can not set code($current_code) to $code.");
    }

    final protected function getCode()
    {
        return $this->result['code'];
    }

    final protected function process($name = NULL, $value = Kit::TYPE_VACANCY, $is_list = FALSE)
    {
        if (FALSE === Kit::isVacancy($value) AND FALSE === Kit::isArray($value))
            throw new UserException('$value of process should be a dict.', [ $name, $value, $is_list ]);
        $this->hasCalledCoreModel = TRUE;
        if (FALSE === Kit::isVacancy($value) AND 
            (FALSE === isset($value[BaseCore::S_OK]) OR TRUE !== $value[BaseCore::S_OK])) {
            $this->fail();
        }
        $isProcessed = TRUE;
        return $this->handleResult('process', $name, $value, $is_list);
    }
    
    final protected function data($name = NULL, $value = Kit::TYPE_VACANCY, $is_list = FALSE)
    {
        return $this->handleResult('data', $name, $value, $is_list);
    }

    final protected function status($name = NULL, $value = Kit::TYPE_VACANCY, $is_list = FALSE)
    {
        return $this->handleResult('status', $name, $value, $is_list);
    }

    final private function handleResult($type, $name, $value, $is_list)
    {
        Kit::ensureString($name, TRUE);
        if (TRUE === Kit::isString($name)) {
            if (TRUE === Kit::isVacancy($value)) // (valid)
                return $this->getResult($type, $name);
            // (valid, valid/NULL)
            return $this->setResult($type, $name, $value, $is_list);
        } else {
            if (TRUE === Kit::isVacancy($value)) // (NULL) / ()
                return $this->getResult($type, NULL);
            // (NULL, valid/NULL)
            throw new UserException('Invalid $value when $name is NULL.', $value);
        }
    }

    final private function setResult($type, $name, $value, $is_list)
    {
        Kit::ensureIn($type, [ 'data', 'status', 'process' ]);
        Kit::ensureString($name);
        Kit::ensureBoolean($is_list);
        if ('' === $name)
            throw new UserException('$name is an empty string.', $type);
        if (TRUE === isset($this->result[$type][$name])) {
            if (TRUE === $is_list) {
                if (TRUE === Kit::isList($this->result[$type][$name])) {
                    $this->result[$type][$name][] = $value;
                } else {
                    $msg = "\$this->result[$type][$name] is a non-empty non-list variable.";
                    throw new UserException($msg, $this->result[$type][$name]);
                }
            } else $this->result[$type][$name] = $value;
        } else {
            if (TRUE === $is_list)
                $this->result[$type][$name] = [ $value ];
            else $this->result[$type][$name] = $value;
        }
        return $value;
    }

    final private function getResult($type, $name)
    {
        if (FALSE === Kit::in($type, [ 'data', 'status', 'process' ]))
            throw new UserException('Invalid $type.', $type);
        Kit::ensureString($name, TRUE);
        if (TRUE === is_null($name))
            return $this->result[$type];
        if (FALSE === isset($this->result[$type][$name])) {
            $msg = "Field($name) does not exist in $type.";
            throw new UserException($msg, $this->result[$type]);
        }
        return $this->result[$type][$name];
    }

    /**
     * Only the following cases are valid:
     * code = 1/2 => 1/2; close_cgi_only = FALSE
     * code = NULL => 3;  close_cgi_only = TRUE
     * @param int     $execution_id
     * @param array   $execution_record
     * @param boolean $close_cgi_only
     */
    final private function succeedRequest($execution_id, $execution_record , $close_cgi_only = FALSE)
    {
        $code = $this->getCode();
        if (TRUE === is_null($code)) {
            if (TRUE === $close_cgi_only) { // code = NULL; close_cgi_only = 1 => 3
                $this->setCode(3);
            } elseif (FALSE === $this->isProcessed) { // code = NULL; close_cgi_only = 0 => error
                $msg = 'Can not succeed the request before service is finished and code is NULL.';
                throw new UserException($msg);
            } else throw new UserException('Processed, but code is NULL.');
            
        } elseif (FALSE === Kit::in($code, [ 1, 2 ])) { // code = 0/3; close_cgi_only = 0/1 => error
            throw new UserException('Invalid code.', $code);
        } elseif (TRUE === $close_cgi_only) { // code = 1/2; close_cgi_only = 1 => error
            throw new UserException('Can not only close cgi after service has finished.', $code);
        } else { // code = 1/2; close_cgi_only = 0 => 1/2
        }
        // Now code must be 1 or 2 or 3.
        $execution_record['success'] = TRUE;
        if (2 === $this->getCode())
            unset($this->result['process']);
        $this->respond($execution_id, $execution_record, 200, $close_cgi_only);
    }

    /**
     * Set code from NULL/1/2 to 0.
     * @param int       $execution_id
     * @param array     $execution_record
     * @param Exception $exception
     */
    final private function failRequest($execution_id, $execution_record, Exception $exception)
    {
        $code = $this->getCode();
        if (FALSE === is_null($code) AND FALSE === Kit::in($code, [ 1, 2 ])) {
            throw new UserException('Can not fail the request because of invalid code.', $code, $exception);
        }
        // Now code must be NULL or 1 or 2.
        $this->setCode(0);
        if (FALSE === Debug::isProduction()) {
            $this->result['input']         = $this->loadInput()->cleanInput();
            $this->result['exception']     = Debug::extractException($exception);
            $this->result['mainException'] = Debug::extractMainException($this->result['exception']);
        }
        $execution_record['success'] = FALSE;
        $this->respond($execution_id, $execution_record, 200); // @TODO: change code
    }

    /**
     * @param int     $execution_id
     * @param array   $execution_record
     * @param int     $status_code
     * @param boolean $close_cgi_only
     */
    final private function respond($execution_id, $execution_record, $status_code, $close_cgi_only = FALSE)
    {
        if (FALSE === $close_cgi_only) {
            $this->result['database'] = [];
            if (2 !== $this->getCode()) {
                $this->result['database']['rollbacked'] = MongoDBCollection::rollback();
            } else $this->result['database']['rollbacked'] = MongoDBCollection::isRollbacked();
            $this->result['database']['changed'] = MongoDBCollection::isChanged();
            Debug::updateExecutionRecord($execution_id, $execution_record);
            Debug::popExecutionId($execution_id);
        }
        if (FALSE === is_null($error = error_get_last()) AND TRUE === Debug::isErrorCared($error)) {
            Debug::handleFatalError($error);
        }
        // header('Content-Type : application/json; charset=utf-8', TRUE, $status_code);
        $this->result['monitor'] = Debug::getMonitor();
        $this->result += Debug::getDebugInfo();
        $this->result += [ 'size' => sprintf('%.2fKB', Kit::len(json_encode($this->result)) / 1024) ];
        $this->result += [ 'datetime' => Kit::toFormat() ];
        $this->result += [ 'timestamp' => Kit::timestampAtNow() ];
        $this->result += [ 'micro_timestamp' => Kit::microTimestampAtNow() ];
        if (TRUE === is_null($this->result['mainException'])) unset($this->result['mainException']);
        if (TRUE === is_null($this->result['monitor'])) unset($this->result['monitor']);
        // $this->loadCore('Log/RequestLog')->addRequestLog(
        //     $execution_record['class'],
        //     $execution_record['method'],
        //     $this->result,
        //     $this->getCode()
        // );
        if (TRUE === Debug::isProduction()) {
            unset($this->result['mainException']);
            unset($this->result['monitor']);
            unset($this->result['database']);
            unset($this->result['process']);
            unset($this->result['time']);
            unset($this->result['memory']);
            unset($this->result['size']);
        }
        Http::json($this->result);
        if (TRUE === $close_cgi_only) {
            fastcgi_finish_request();
            // DO NOT exit in order to run the subsequent scripts.
        } else {
            if (TRUE === $this->needLock) $this->QueueCore->pop();
            exit();
        }
    }
}