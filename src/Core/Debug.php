<?php

namespace Ilex\Core;

use \Exception;
use \ReflectionFunction;
use \ReflectionMethod;
use \Ilex\Core\Loader;
use \Ilex\Lib\Http;
use \Ilex\Lib\Kit;
use \Ilex\Lib\UserException;
use \Ilex\Lib\MongoDB\MongoDBCollection;

/**
 * Class Debug
 * A class handling debug operations.
 * @package Ilex\Core
 *
 * @property private static array  $config
 * @property private static string $environment
 * @property private static array  $executionIdStack
 * @property private static array  $executionRecordStack
 * @property private static int    $flag
 * 
 * @method final public static int      addExecutionRecord(mixed $execution_record)
 * @method final public static int      countExecutionRecord()
 * @method final public static array    extractException(Exception $exception)
 * @method final public static array    getExecutionRecordStack()
 * @method final public static          initialize()
 * @method final public static int      popExecutionId(int $execution_id)
 * @method final public static          pushExecutionId(int $execution_id)
 * @method final public static          updateExecutionRecord(int $execution_id, array $execution_record)
 *
 * @method final private static int|NULL peekExecutionId()
 * @method final private static array    recoverBacktraceParameters(array $backtrace)
 * @method final private static array    recoverFunctionParameters(string|NULL $class_name
 *                                           , string|Closure $function_name, array $arg_list)
 * @method final private static array    extractInitiator(array $trace)
 */
final class Debug
{
    const D_NONE          = 0;
    const D_E_DETAIL      = 1;
    const D_E_DETAIL_ARGS = 2;
    const D_E_DETAIL_MORE = 4;
    const D_E_INITIATOR   = 8;
    const D_E_TRACE       = 16;
    const D_E_TRACE_ARGS  = 32;
    const D_E_FILE        = 64;
    const D_E_ALL         = 1023;

    const E_DEVELOPMENT = 'DEVELOPMENT';
    const E_PRODUCTION  = 'PRODUCTION';
    const E_TEST        = 'TEST';

    const M_BYTE     = 'BYTE';
    const M_KILOBYTE = 'KILOBYTE';
    const M_MEGABYTE = 'MEGABYTE';
    const M_GIGABYTE = 'GIGABYTE';

    const T_MICROSECOND = 'MICROSECOND';
    const T_MILLISECOND = 'MILLISECOND';
    const T_SECOND      = 'SECOND';
    const T_MINUTE      = 'MINUTE';

    private static $errorTypes     = E_ALL;
    private static $isErrorHandled = FALSE;

    private static $config               = NULL;
    private static $environment          = self::E_PRODUCTION;
    private static $executionIdStack     = [ ];
    private static $executionRecordStack = [ ]; // @TODO: disable it in production mode to save memory
    private static $startTime            = NULL;
    private static $monitor              = NULL;

    final public static function setErrorTypes($error_types)
    {
        Kit::ensureInt($error_types, FALSE, FALSE);
        self::$errorTypes = $error_types;
    }

    final public static function initialize()
    {
        self::$config = [
            'trace' => [
                '@-1' => self::D_NONE,
            ],
            'exception' => [
                '@-1' => self::D_NONE,
            ],
        ];
        $raw_config = Loader::loadInput()->input('Debug', NULL);
        if (TRUE === Kit::isDict($raw_config)) $config = $raw_config;
        elseif (TRUE === is_null($raw_config)) $config = [ ];
        elseif (TRUE === Kit::isString($raw_config, FALSE, TRUE)) {
            if ('' === $raw_config) $config = [ ];
            else {
                $config = json_decode($raw_config, TRUE);
                if (TRUE === is_null($config) AND Kit::len($raw_config) > 0)
                    throw new UserException(json_last_error_msg(), $raw_config);
            }
        } else throw new UserException('Invalid $raw_config.', $raw_config);
        Kit::ensureDict($config);
        if (TRUE === isset($config['t']))
            Kit::update(self::$config['trace'], $config['t']);
        if (TRUE === isset($config['e']))
            Kit::update(self::$config['exception'], $config['e']);
        self::$executionIdStack     = [];
        self::$executionRecordStack = [];
        self::$startTime            = $_SERVER['REQUEST_TIME_FLOAT'];
    }

    final public static function resetStartTime()
    {
        self::$startTime = Kit::microTimestampAtNow();
    }

    final private static function respondOnFail($exception_or_error, $is_error = FALSE)
    {
        Kit::ensureBoolean($is_error);
        $result = [
            'database' => [
                'rollbacked' => MongoDBCollection::rollback(),
                'changed'    => MongoDBCollection::isChanged(),
            ],
            'code'     => 0,
        ];
        if (FALSE === $is_error) {
            try {
                $exception = self::extractException($exception_or_error);
            } catch (Exception $e) {
                $exception = self::extractException($e);
                $result['lastException'] = $exception_or_error;
            }
            $result['mainException'] = self::extractMainException($exception);
            $result['exception']     = $exception[0];
            $result['monitor']       = self::getMonitor();
        } else {
            $result['error']   = $exception_or_error;
            if (TRUE === $exception_or_error instanceof UserException)
                $result['detail'] = $exception_or_error->getDetail();
            $result['monitor'] = self::getMonitor();
        }
        $result += self::getDebugInfo();
        if (TRUE === is_null($result['mainException'])) unset($result['mainException']);
        if (TRUE === is_null($result['monitor'])) unset($result['monitor']);
        // Loader::loadCore('Log/RequestLog')->addRequestLog(NULL, NULL, $result, 0);
        if (TRUE === self::isProduction()) {
            unset($result['error']);
            unset($result['exception']);
            unset($result['lastException']);
            unset($result['mainException']);
            unset($result['database']);
            unset($result['monitor']);
            unset($result['time']);
            unset($result['memory']);
            unset($result['size']);
        } else {
            Http::json($result);
        }
        Loader::loadCore('Queue/Queue')->pop();
    }

    final public static function isErrorCared($error)
    {
        Kit::ensureDict($error);
        return (self::$errorTypes & $error['type']) === $error['type'];
    }

    final public static function handleFatalError($error = NULL) {
        Kit::ensureDict($error, TRUE);
        if (TRUE === is_null($error)) $error = error_get_last();
        if (FALSE === self::$isErrorHandled
            AND FALSE === is_null($error)
            AND (TRUE === self::isErrorCared($error))
        ) {
            $error['type'] = self::polishErrorType($error['type']);
            self::respondOnFail($error, TRUE);
        }
        self::$isErrorHandled = TRUE;
        exit();
    }

    final public static function handleUncaughtException(Exception $e)
    {
        self::respondOnFail($e);
        self::$isErrorHandled = TRUE;
        exit();
    }

    final public static function monitor($field_name, $field_value, $is_list = FALSE)
    {
        Kit::ensureString($field_name);
        if (TRUE === is_null(self::$monitor))
            self::$monitor = [ ];
        if (TRUE === $is_list) {
            if (FALSE === isset(self::$monitor[$field_name]))
                self::$monitor[$field_name] = [ $field_value ];
            else self::$monitor[$field_name][] = $field_value;
        } else self::$monitor[$field_name] = $field_value;
    }

    final public static function getMonitor()
    {
        return self::$monitor;
    }

    final public static function getDebugInfo()
    {
        return [
            // 'trace'  => self::getExecutionRecordStack(),
            'memory' => self::getMemoryUsed(),
            'time'   => self::getTimeUsed(),
        ];
    }

    final public static function getMemoryUsed($unit = self::M_MEGABYTE, $to_string = TRUE)
    {
        Kit::ensureIn($unit, [ self::M_BYTE, self::M_KILOBYTE, self::M_MEGABYTE, self::M_GIGABYTE ]);
        Kit::ensureBoolean($to_string);
        $result = memory_get_peak_usage(TRUE);
        $result *= [
            self::M_BYTE     => 1,
            self::M_KILOBYTE => 1.0 / 1024,
            self::M_MEGABYTE => 1.0 / (1024 * 1024),
            self::M_GIGABYTE => 1.0 / (1024 * 1024 * 1024),
        ][$unit];
        if (TRUE === $to_string)
            $result = sprintf([
                self::M_BYTE     => '%dB',
                self::M_KILOBYTE => '%.1fKB',
                self::M_MEGABYTE => '%.1fMB',
                self::M_GIGABYTE => '%.1fGB',
            ][$unit], $result);
        return $result;
    }

    final public static function getTimeUsed($unit = self::T_MILLISECOND, $to_string = TRUE)
    {
        Kit::ensureIn($unit, [ self::T_MICROSECOND, self::T_MILLISECOND, self::T_SECOND, self::T_MINUTE ]);
        Kit::ensureBoolean($to_string);
        $result = Kit::microTimestampAtNow() - self::$startTime;
        $result *= [
            self::T_MICROSECOND => 1000 * 1000,
            self::T_MILLISECOND => 1000,
            self::T_SECOND      => 1,
            self::T_MINUTE      => 1.0 / 60,
        ][$unit];
        if (TRUE === $to_string)
            $result = sprintf([
                self::T_MICROSECOND => '%dmms',
                self::T_MILLISECOND => '%.1fms',
                self::T_SECOND      => '%.1fs',
                self::T_MINUTE      => '%.1fm',
            ][$unit], $result);
        return $result;
    }

    final public static function setEnvironmentToDevelopment()
    {
        self::$environment = self::E_DEVELOPMENT;
    }

    final public static function setEnvironmentToProduction()
    {
        self::$environment = self::E_PRODUCTION;
    }

    final public static function setEnvironmentToTest()
    {
        self::$environment = self::E_TEST;
    }

    final public static function isDevelopment()
    {
        return self::$environment === self::E_DEVELOPMENT;
    }

    final public static function isProduction()
    {
        return self::$environment === self::E_PRODUCTION;
    }

    final public static function isTest()
    {
        return self::$environment === self::E_TEST;
    }

    final private static function checkExceptionDisplay($index, $flag)
    {
        Kit::ensureNonNegativeInt($index);
        Kit::ensureNonNegativeInt($flag);
        if (TRUE === isset(self::$config['exception']["@$index"]))
            $exception_flag = self::$config['exception']["@$index"];
        else $exception_flag = self::$config['exception']["@-1"];
        return ($flag & $exception_flag) === $flag;
    }

    /**
     * Pushs $execution_id into the execution id stack.
     * @param int $execution_id
     */
    final public static function pushExecutionId($execution_id)
    {
        Kit::ensureNonNegativeInt($execution_id);
        self::$executionIdStack[] = $execution_id;
    }

    /**
     * Pops $execution_id out of the execution id stack.
     * @param int $execution_id
     */
    final public static function popExecutionId($execution_id)
    {
        Kit::ensureNonNegativeInt($execution_id);
        if (0 === Kit::len(self::$executionIdStack)) {
            // throw new UserException('$executionIdStack is empty.', 1);
            self::monitor('exception_msg', '$executionIdStack is empty.', TRUE);
            break;
        }
        if (Kit::last(self::$executionIdStack) !== $execution_id) {
            $msg = "\$execution_id($execution_id) does not match the top of \$executionIdStack.";
            throw new UserException($msg, self::$executionIdStack);
        }
        Kit::popList(self::$executionIdStack);
    }

    /**
     * Peeks the top execution id of the stack.
     * @return int|NULL $execution_id
     */
    final private static function peekExecutionId()
    {
        if (0 === Kit::len(self::$executionIdStack)) {
            // throw new UserException('$executionIdStack is empty.', 1);
            return NULL;
        }
        return Kit::last(self::$executionIdStack);
    }

    /**
     * Adds execution record to the stack.
     * @param array $execution_record
     * @return int Current id of the execution record.
     */
    final public static function addExecutionRecord($execution_record)
    {
        Kit::ensureArray($execution_record);
        if (TRUE === isset($execution_record['args'])) {
            $class_name  = $execution_record['class'];
            $method_name = $execution_record['method'];
            $arg_list    = $execution_record['args'];
            try {
                $param_list = self::recoverFunctionParameters($class_name, $method_name, $arg_list);
            } catch (Exception $e) {
                $param_list = [
                    'raw_args' => $arg_list,
                    'recover'  => self::extractException($e),
                ];
                // throw new UserException('Method(recoverFunctionParameters) failed.', NULL, $e);
            }
            $execution_record['params'] = $param_list;
        }
        $parent_execution_id = self::peekExecutionId();
        if (TRUE === is_null($parent_execution_id)) {
            $execution_record['parent_execution_id'] = -1;
            $execution_record['indent']              = '';
        } else {
            $indent = self::$executionRecordStack[$parent_execution_id]['indent'];
            $execution_record['parent_execution_id'] = $parent_execution_id;
            $execution_record['indent']              = $indent . ' ';
        }
        $execution_record['time_used']   = self::getTimeUsed(self::T_MILLISECOND, FALSE);
        $execution_record['memory_used'] = self::getMemoryUsed(self::M_KILOBYTE, FALSE);
        // $execution_record = self::simplifyExecutionRecord($execution_record);
        self::$executionRecordStack[] = $execution_record;
        return self::countExecutionRecord() - 1;
    }

    /**
     * Updates the $execution_id 'th execution record in the stack.
     * @param int   $execution_id
     * @param mixed $execution_record
     */
    final public static function updateExecutionRecord($execution_id, $execution_record)
    {
        Kit::ensureNonNegativeInt($execution_id);
        Kit::ensureArray($execution_record);
        if ($execution_id >= Kit::len(self::$executionRecordStack))
            throw new UserException("\$execution_id($execution_id) overflows \$executionRecordStack.");
        // $execution_record = self::simplifyExecutionRecord($execution_record);
        self::$executionRecordStack[$execution_id] = array_merge(
            self::$executionRecordStack[$execution_id], $execution_record);
    }

    // final private static function simplifyExecutionRecord($execution_record)
    // {
    //     return [
    //         'indent'              => $execution_record['indent'],
    //         'parent_execution_id' => $execution_record['parent_execution_id'],
    //         'success'             => $execution_record['success'],
    //         'handler_prefix'      => $execution_record['handler_prefix'],
    //         'handler_suffix'      => $execution_record['handler_suffix'],
    //         'method'              => $execution_record['method'],
    //     ];
    // }

    /**
     * Counts the execution record stack.
     * @return int
     */
    final public static function countExecutionRecord()
    {
       return Kit::len(self::$executionRecordStack);
    }

    /**
     * Gets the execution record stack.
     * @return array
     */
    final public static function getExecutionRecordStack()
    {
        $result = self::$executionRecordStack;
        $index = 0;
        while ($index < Kit::len($result)) {
            $result[$index] = sprintf('%5.1fms %.0fKB %s%02d.(%02d) (%s) %10s %10s :: %s',
                $result[$index]['time_used'],
                $result[$index]['memory_used'],
                $result[$index]['indent'],
                $index,
                $result[$index]['parent_execution_id'],
                (TRUE === $result[$index]['success']) ? 'ok' : 'error',
                // $result[$index]['class'],
                $result[$index]['handler_prefix'],
                $result[$index]['handler_suffix'],
                $result[$index]['method']
            );
            $index++;
        }
        // $result = Kit::slice($result, 0, 10);
            // 'parent_execution_id'
            // 'indent'
            // 'success'
        // 'class'
            // 'method'

        // 'input'
        // 'validateInput'
        // 'sanitizeInput'
        // 'params'
        // 'validateArgs'
        // 'sanitizeArgs'

        // 'validateModelPrivilege'
        // 'method_accessibility'
        // 'method_visibility'
        // 'declaring_class'
        // 'initiator_class'
        // 'initiator_type'
            // 'handler_prefix'
            // 'handler_suffix'

        // 'result'
        // 'validateResult'
        // 'sanitizeResult'
        // 'service_result'
        // 'validateServiceResult'
        // 'sanitizeServiceResult'

        // 'is_time_consuming'
        return $result;
    }

    final public static function extractMainException($exception)
    {
        Kit::ensureArray($exception);
        return NULL;
    }

    /**
     * Extracts useful info from an exception.
     * @param Exception $exception
     * @return array
     */
    final public static function extractException(Exception $exception)
    {
        $result = [ self::extractExceptionIteratively($exception) ];
        $index = 0;
        while ($index < Kit::len($result)) {
            if (TRUE === isset($result[$index]['previous']))
                $result[] = $result[$index]['previous'];
            try {
                $handler_prefix = Loader::getHandlerPrefixFromPath($result[$index]['class']);
                $handler_suffix = Loader::getHandlerSuffixFromPath($result[$index]['class']);
                $handler = sprintf('%10s %10s', $handler_prefix, $handler_suffix);
            } catch (Exception $e) {
                $handler = $result[$index]['class'];
            }
            $tmp = [
                'msg' => sprintf('%d. %s %s %s (%d) ==> [%s]', 
                    $index,
                    $handler,
                    $result[$index]['type'],
                    $result[$index]['function'],
                    $result[$index]['line'],
                    $result[$index]['message']
                ),
                'context' => $result[$index]['trace'][0],
            ];
            // if (TRUE === self::checkExceptionDisplay($index, self::D_E_DETAIL)) {
                if (FALSE === Kit::isVacancy($result[$index]['detail'])) {
                    $tmp['detail'] = $result[$index]['detail'];
                    if (FALSE === self::checkExceptionDisplay($index, self::D_E_DETAIL_MORE)
                        AND TRUE === Kit::isArray($tmp['detail'])) {
                        // $tmp['detail'] = Kit::extract($tmp['detail'], [
                        //     'class',
                        //     'method',
                        //     'args',
                        //     'args_sanitization_result',
                        //     'declaring_class',
                        // ], FALSE);
                        if (FALSE === is_null($tmp['detail']['class'])
                            AND FALSE === is_null($tmp['detail']['method'])) {
                            $tmp['detail']['handler'] = sprintf('        %s :: %s',
                                $tmp['detail']['class'], $tmp['detail']['method']);
                            unset($tmp['detail']['class']);
                            unset($tmp['detail']['method']);
                        }
                    }
                    if (FALSE === self::checkExceptionDisplay($index, self::D_E_DETAIL_ARGS)
                        AND TRUE === Kit::isArray($tmp['detail'])) {
                        unset($tmp['detail']['args']);
                        unset($tmp['detail']['args_sanitization_result']);
                    }
                }
                // else $tmp['detail'] = NULL;
            // }
            if (TRUE === self::checkExceptionDisplay($index, self::D_E_INITIATOR)) {
                $tmp['initiator'] = sprintf('%s :: %s',
                    $result[$index]['initiator_class'], $result[$index]['initiator_function']);
            }
            // if (TRUE === self::checkExceptionDisplay($index, self::D_E_TRACE)) {
                // if (TRUE === self::checkExceptionDisplay($index, self::D_E_TRACE_ARGS))
                    $tmp['trace'] = $result[$index]['trace'];
                // else $tmp['trace'] = Kit::columnsExclude($result[$index]['trace'], [ 'params', 'args' ]);
            // }
            if (TRUE === self::checkExceptionDisplay($index, self::D_E_FILE)) {
                $tmp['file'] = $result[$index]['file'];
            }
            if (1 === Kit::len($tmp))
                $result[$index] = $tmp['msg'];
            else $result[$index] = $tmp;
            $index++;
        }
            // message
            // file
            // line
            // class
            // function
        // params
        // initiator_class
        // initiator_function
            // trace
            // detail
        return Kit::reversed($result);
    }

    /**
     * Extracts useful info from an exception iteratively.
     * @param Exception $exception
     * @return array
     */
    final private static function extractExceptionIteratively(Exception $exception)
    {
        $result = [
            'message' => $exception->getMessage(),
            'file'    => $exception->getFile(),
            'line'    => $exception->getLine(),
            'trace'   => Kit::columnsExclude(
                self::recoverBacktraceParameters($exception->getTrace()), 'args', TRUE),
        ];
        // add fields: 'initiator_class', 'initiator_function'
        $result += self::extractInitiator($result['trace']);
        // add fields: 'class', 'type', 'function', 'params'
        $result += $result['trace'][0];
        $result['trace'] = self::polishTrace($result['trace']);

        if (FALSE === (FALSE === ($exception instanceof UserException) 
            OR (TRUE === ($exception instanceof UserException)
                AND TRUE === is_null($exception->getDetail())))
        ) {
            $result['detail'] = $exception->getDetail();
        }
        if (FALSE === is_null($exception->getPrevious()))
            $result['previous'] = self::extractExceptionIteratively($exception->getPrevious());
        return $result;
        // 'message'
        // 'detail'
        // 'file'
        // 'line'
        // 'class'
        // 'function'
        // 'params'
        // 'initiator_class'
        // 'initiator_function'
        // 'trace'
        // 'previous'
    }
    
    /**
     * Recovers parameters of the function in the records of a backtrace.
     * @param array $backtrace
     * @return array
     */
    final private static function recoverBacktraceParameters($backtrace)
    {
        Kit::ensureArray($backtrace);
        foreach ($backtrace as $index => $record) {
            try {
                $backtrace[$index]['params'] = self::recoverFunctionParameters(
                    $record['class'],
                    $record['function'],
                    $record['args']
                );
            } catch (Exception $e) {
                $backtrace[$index]['params'] = [
                    'raw_args' => $record['args'],
                    'recover'  => self::extractException($e),
                ];
                // throw new UserException('Method(recoverFunctionParameters) failed.', NULL, $e);
            }
        };
        return $backtrace;
    }

    /**
     * Recovers parameters of a function or a method in a class.
     * @param string|NULL    $class_name
     * @param string|Closure $function_name
     * @param array          $arg_list
     * @return array
     */
    final private static function recoverFunctionParameters($class_name, $function_name, $arg_list)
    {
        Kit::ensureString($class_name, TRUE);
        Kit::ensureArray($arg_list);
        $param_mapping = [];
        try {
            if (TRUE === is_null($class_name))
                $reflection_function = new ReflectionFunction($function_name);
            else $reflection_function = new ReflectionMethod($class_name, $function_name);
        } catch (Exception $e) {
            // throw new UserException('Reflection failed.', [ $class_name, $function_name ], $e);
            return NULL;
        }
        foreach ($reflection_function->getParameters() as $position => $param) {
            $param_name = $param->getName();
            // var_dump([
            //     'index'                       => $position, 
            //     'position'                    => $param->getPosition(), 
            //     'name'                        => $param->getName(), 
            //     'is_optional'                 => $param->isOptional(),
            //     'is_passed_by_reference'      => $param->isPassedByReference(),
            //     'allows_null'                 => $param->allowsNull(), 
            //     'default_value_constant_name' => $param->isDefaultValueConstant(),
            //          // ? $param->getDefaultValueConstantName() : 'default value is not const', 
            //     'default_value'               => $param->isDefaultValueAvailable(),
            //          // ? $param->getDefaultValue() : 'no default value', 
            //     'arg'                         => $arg_list[$position],
            // ]);
            if ($position + 1 > Kit::len($arg_list)) {
                try {
                    // @TODO: check if it will fail
                    $param_mapping[$param_name] = $param->getDefaultValue();
                } catch (Exception $e) {
                    // @TODO: check it, add alert to postman
                    // throw new UserException('Method(getDefaultValue) failed.', NULL, $e);
                    $param_mapping[$param_name] = '[GET_DEFAULT_VALUE_FAILED]';
                }
            }
            else $param_mapping[$param_name] = $arg_list[$position];
        }
        return $param_mapping;
    }

    /**
     * Extracts initiator info from a trace.
     * @param array $trace
     * @return array
     */
    final private static function extractInitiator($trace)
    {
        Kit::ensureArray($trace);
        if (Kit::len($trace) <= 1) $index = NULL;
        else {
            // @todo: add comment to this
            if (TRUE === Kit::in($trace[0]['function'], [
                '__call', 'call', 'callParent', 'execute'
            ])) {
                if ($trace[0]['args'][0] !== $trace[1]['function']) $index = 1;
                else {
                    if (Kit::len($trace) <= 2) $index = NULL;
                    else $index = 2;
                }
            } elseif (TRUE === Kit::in($trace[1]['function'], [
                'call_user_func_array',
                'call_user_method_array',
                'call_user_func',
                'call_user_method'
            ])) {
                if (Kit::len($trace) <= 2) $index = NULL;
                else $index = 2;
            } else $index = 1;
        }
        return [
            'initiator_class'    => TRUE === is_null($index) ? NULL : $trace[$index]['class'],
            'initiator_function' => TRUE === is_null($index) ? NULL : $trace[$index]['function'],
        ];
    }

    /**
     * Polishs a trace by combining useful fields.
     * @param array $trace
     * @return array
     */
    final private static function polishTrace($trace)
    {
        Kit::ensureArray($trace);
        $result = [];
        foreach ($trace as $index => $record) {
            $record['index'] = Kit::len($trace) - $index - 1;
            if (TRUE === isset($record['file'])) {
                $record['initiator'] = sprintf('%s (%d)', $record['file'], $record['line']);
                $record = Kit::exclude($record, [ 'file', 'line' ]);
            }
            if (TRUE === isset($record['class'])) {
                $record['handler'] = sprintf('%s %s ', $record['class'], $record['type']);
                $record = Kit::exclude($record, [ 'class', 'type' ]);
            } else $record['handler'] = '';
            if (TRUE === Kit::in($record['function'], [
                    'call_user_func_array',
                    'execute',
                    'call',
                    '__call',

                ])) continue;
            $record['handler'] .= $record['function'];
            unset($record['function']);
            $result[] = $record;
        }
        return $result;
    }

    final private static function polishErrorType($error_type) 
    {
        Kit::ensureNonNegativeInt($error_type);
        return [
            E_ERROR             => 'E_ERROR', 
            E_WARNING           => 'E_WARNING', 
            E_PARSE             => 'E_PARSE', 
            E_NOTICE            => 'E_NOTICE', 
            E_CORE_ERROR        => 'E_CORE_ERROR', 
            E_CORE_WARNING      => 'E_CORE_WARNING', 
            E_COMPILE_ERROR     => 'E_COMPILE_ERROR', 
            E_COMPILE_WARNING   => 'E_COMPILE_WARNING', 
            E_USER_ERROR        => 'E_USER_ERROR', 
            E_USER_WARNING      => 'E_USER_WARNING', 
            E_USER_NOTICE       => 'E_USER_NOTICE', 
            E_STRICT            => 'E_STRICT', 
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR', 
            E_DEPRECATED        => 'E_DEPRECATED', 
            E_USER_DEPRECATED   => 'E_USER_DEPRECATED', 
        ][$error_type];
    }
}