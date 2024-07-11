<?php

namespace Ilex\Base\Model\System;

use \Ilex\Lib\Container;
use \Ilex\Lib\Kit;
use \Ilex\Lib\UserException;

/**
 * Class Input
 * Encapsulation of system input, such as $_GET, $_POST.
 * @package Ilex\Base\Model\System
 * 
 * @property private static \Ilex\Lib\Container $getData
 * @property private static \Ilex\Lib\Container $postData
 * @property private static \Ilex\Lib\Container $inputData
 * 
 * @method final public                __construct()
 * @method final public static boolean clear(string $name = NULL)
 * @method final public static mixed   get(string $key = NULL, mixed $default = NULL)
 * @method final public static boolean hasGet(array $key_list)
 * @method final public static boolean hasInput(array $key_list)
 * @method final public static boolean hasPost(array $key_list)
 * @method final public static mixed   input(string $key = NULL, mixed $default = NULL)
 * @method final public static boolean merge(string $name, array $data)
 * @method final public static array   missGet(array $key_list)
 * @method final public static array   missInput(array $key_list)
 * @method final public static array   missPost(array $key_list)
 * @method final public static mixed   post(string $key = NULL, mixed $default = NULL)
 * @method final public static mixed   setInput(mixed $key, mixed $value)
 */
final class Input
{
    private static $getData;
    private static $postData;
    private static $inputData;

    /**
     * Encapsulates global variables.
     */
    final public function __construct()
    {
        self::$getData   = new Container();
        self::$postData  = new Container();
        // self::$cookieData  = new Container();
        self::$inputData = new Container();
        self::merge('get', $_GET);
        self::merge('post', $_POST);
        // self::merge('cookie', $_COOKIE);
        if (Kit::len($_FILES) > 0) {
            $data = [ 'FILES' => [] ];
            foreach ($_FILES as $field_name => $package) {
                $package['field_name'] = $field_name;
                $name = $package['name'];
                if (FALSE === strpos($name, '_')) {
                    if(!move_uploaded_file($package['tmp_name'], '/data/Taleopard-CDN/Upload/' . $name)){
                        $package['fail_to_move'] = TRUE;
                    }
                } else {
                    $folder_name = Kit::split('_', $name)[0];
                    $package['folder_name'] = $folder_name;
                    if ('V' === $folder_name) {
                        $student_no =  Kit::split('_', $name)[2];
                        $package['student_no'] = $student_no;
                        $import_file_folder = "/data/Taleopard-CDN/Upload/$folder_name/$student_no/";
                    } else {
                        $import_file_folder = "/data/Taleopard-CDN/Upload/$folder_name/";
                    }
                    if (!file_exists($import_file_folder)) mkdir($import_file_folder, 0755);

                    $import_file_path = $import_file_folder . $name;
                    if(!move_uploaded_file($package['tmp_name'], $import_file_path)){
                        $package['fail_to_move'] = TRUE;
                    }
                    // $export_file_folder = "/data/Taleopard-CDN/Result/$folder_name/";
                    // if (!file_exists($import_file_folder)) mkdir($export_file_folder, 0755);
                    // $convert_result = shell_exec("python3 /home/taleopard/script/wav2mp3.py $import_file_path $export_file_folder");
                    // $convert_result = [];
                    // exec("python3 /home/taleopard/script/wav2mp3.py $import_file_path $export_file_folder", $convert_result, $return_code);
                    // $package['convert_result'] = $convert_result;
                    // $package['return_code'] = $return_code;
                }
                $data['FILES'][] = $package;
            }
        } else {
            $opts = [ 'http' => [ 'timeout' => 60 ] ];
            $context = stream_context_create($opts);
            $input = file_get_contents('php://input', FALSE, $context);
            $data  = json_decode($input, TRUE);
            if (TRUE === is_null($data) AND Kit::len($input) > 0)
                throw new UserException(json_last_error_msg(), $input);
        }
        if (FALSE === is_null($data)) self::merge('post', $data);
        $limit = 10000000;
        if (Kit::len(json_encode(self::input())) > $limit) 
            throw new UserException("Input size exceeds limit($limit).");
    }

    final public static function uri()
    {
        return Kit::ensureString(self::input()[0]);
    }

    final public static function token()
    {
        return self::input('token');
    }

    final public static function cleanInput()
    {
        $result = self::input();
        unset($result[0]);
        unset($result['_url']);
        unset($result['token']);
        unset($result['error']);
        unset($result['Debug']);
        return $result;
    }

    /**
     * If $name is NOT assigned, $getData, $postData and $inputData will both be cleared.
     * @param string $name
     * @return boolean
     */
    final public static function clear($name = NULL)
    {
        Kit::ensureString($name, TRUE);
        if (FALSE === is_null($name)) {
            Kit::ensureIn($name, ['get', 'post', 'input']);
            $name .= 'Data';
            self::$$name->clear();
            return TRUE;
        } else {
            self::$getData->clear();
            self::$postData->clear();
            self::$inputData->clear();
            return TRUE;
        }
    }

    /**
     * @param array $key_list
     * @return boolean
     */
    final public static function hasInput($key_list)
    {
        Kit::ensureArray($key_list); // @CAUTION
        return call_user_func_array([self::$inputData, 'has'], $key_list);
    }
    
    /**
     * @param array $key_list
     * @return boolean
     */
    final public static function hasGet($key_list)
    {
        Kit::ensureArray($key_list); // @CAUTION
        return call_user_func_array([self::$getData, 'has'], $key_list);
    }

    /**
     * @param array $key_list
     * @return boolean
     */
    final public static function hasPost($key_list)
    {
        Kit::ensureArray($key_list); // @CAUTION
        return call_user_func_array([self::$postData, 'has'], $key_list);
    }

    /**
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    final public static function input($key = NULL, $default = NULL)
    {
        return self::$inputData->get($key, $default);
    }

    /**
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    final public static function get($key = NULL, $default = NULL)
    {
        return self::$getData->get($key, $default);
    }

    /**
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    final public static function post($key = NULL, $default = NULL)
    {
        return self::$postData->get($key, $default);
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @return mixed
     */
    final public static function setInput($key, $value)
    {
        return self::$inputData->set($key, $value);
    }

    /**
     * @param string $name
     * @param array  $data
     * @return boolean
     */
    final public static function merge($name, $data)
    {
        Kit::ensureIn($name, ['get', 'post', 'input']);
        $name .= 'Data';
        self::$$name->merge($data);
        /* 
        CAUTION: 
            The + operator returns the right-hand array appended to the left-hand array;
            for keys that exist in both arrays, the elements from the left-hand array will be used,
            and the matching elements from the right-hand array will be ignored.
        
            array_merge — Merge one or more arrays
            array array_merge ( array $array1 [, array $... ] )
            Merges the elements of one or more arrays together so that the values of one
            are appended to the end of the previous one. It returns the resulting array.
            If the input arrays have the same string keys, then the later value for that key will 
            overwrite the previous one. If, however, the arrays contain numeric keys,
            the later value will not overwrite the original value, but will be appended.
            Values in the input array with numeric keys will be renumbered with
            incrementing keys starting from zero in the result array.
        */
        if ($name !== 'input') {
            self::$inputData->merge(self::get());
            self::$inputData->merge(self::post());
        }
        return TRUE;
    }

    /**
     * @param array $key_list
     * @return array
     */
    final public static function missInput($key_list)
    {
        return self::$inputData->miss($key_list);
    }

    /**
     * @param array $key_list
     * @return array
     */
    final public static function missGet($key_list)
    {
        return self::$getData->miss($key_list);
    }

    /**
     * @param array $key_list
     * @return array
     */
    final public static function missPost($key_list)
    {
        return self::$postData->miss($key_list);
    }
}
