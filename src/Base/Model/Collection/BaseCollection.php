<?php

namespace Ilex\Base\Model\Collection;

use \Ilex\Core\Loader;
use \Ilex\Lib\Kit;
use \Ilex\Base\Model\Wrapper\CollectionWrapper;
use \Ilex\Base\Model\Wrapper\EntityWrapper;
use \Ilex\Base\Model\BaseModel;

/**
 * Class BaseCollection
 * Base class of collection models of Ilex.
 * @package Ilex\Base\Model\Collection
 */
abstract class BaseCollection extends BaseModel
{
    protected static $methodsVisibility = [
        self::V_PUBLIC => [
            'createEntity',
            // 'checkExistsId',
            // 'checkExistsSignature',
        ],
        self::V_PROTECTED => [
            // 'countAll',
            // 'getTheOnlyOneIdBySignature',
            // 'getTheOnlyOneId',
            // 'getTheOnlyOneField',
            // 'addOneWithTypeAndSignatureThenGetId',
            // 'addOneThenGetId',
            // 'updateOneWithAddToSetById',
            // 'getTheOnlyOneContent',
            // 'getTheOnlyOneData',
            // 'getTheOnlyOneInfo',
            // 'getTheOnlyOneMeta',
        ],
    ];

    private $collectionWrapper = NULL;

    // const COLLECTION_NAME = NULL; // should set in subclass
    // const ENTITY_PATH     = NULL; // should set in subclass

    final public function __construct()
    {
        $collection_name = static::COLLECTION_NAME;
        $entity_path     = static::ENTITY_PATH;
        Kit::ensureString($collection_name, TRUE);
        if (TRUE === is_null($collection_name)) {
            // throw new UserException('COLLECTION_NAME is not set.'); // @CAUTION
        } else {
            $this->collectionWrapper = CollectionWrapper::getInstance($collection_name, $entity_path);
        }
    }

    final protected function ensureInitialized()
    {
        if (FALSE === isset($this->collectionWrapper)
            OR FALSE === $this->collectionWrapper instanceof CollectionWrapper)
            throw new UserException('This collection has not been initialized.');
    }

    final protected function createEntity()
    {
        $this->call('ensureInitialized');
        $entity_name       = $this->collectionWrapper->getEntityName();
        $entity_class_name = $this->collectionWrapper->getEntityClassName();
        $collection_name   = $this->collectionWrapper->getCollectionName();
        $entity_wrapper    = EntityWrapper::getInstance($collection_name);
        return new $entity_class_name($entity_wrapper, $entity_name);
    }

    // final protected function checkExistsId($_id)
    // {
    //     return $this->call('checkExistsField', '_id', $_id);
    // }

    // final protected function checkExistsSignature($signature)
    // {
    //     return $this->call('checkExistsField', 'Signature', $signature);
    // }

    // final protected function checkExistsField($path_of_field, $field_value)
    // {
    //     $criterion = [
    //         $path_of_field => $field_value,
    //     ];
    //     return $this->collection->checkExistence($criterion);
    // }

    // final protected function countAll()
    // {
    //     return $this->collection->count();
    // }

    // final protected function getTheOnlyOneIdBySignature($signature)
    // {
    //     return $this->call('getTheOnlyOneIdByField', 'Signature', $signature);
    // }

    // final protected function getTheOnlyOneIdByField($path_of_field, $field_value)
    // {
    //     $criterion = [
    //         $path_of_field => $field_value,
    //     ];
    //     return $this->call('getTheOnlyOneId', $criterion);
    // }

    // final protected function getTheOnlyOneId($criterion)
    // {
    //     return $this->call('getTheOnlyOneField', $criterion, '_id');
    // }

    // final protected function getTheOnlyOneField($criterion, $path_of_field)
    // {
    //     $projection = [
    //         $path_of_field => 1,
    //     ];
    //     $document = $this->call('getTheOnlyOne', $criterion, $projection);
    //     $field_value = $document;
    //     foreach (Kit::split('.', $path_of_field) as $key) {
    //         if (FALSE === isset($field_value[$key]))
    //             throw new UserException("Can not find field with path($path_of_field).", $document);
    //         $field_value = $field_value[$key];
    //     }
    //     return $field_value;
    // }
    
    // final protected function getTheOnlyOneContent($criterion)
    // {
    //     $document = $this->call('getTheOnlyOne', $criterion);
    //     return $document['Content'];
    // }

    // final protected function getTheOnlyOneData($criterion)
    // {
    //     $document = $this->call('getTheOnlyOne', $criterion);
    //     return $document['Content']['Data'];
    // }

    // final protected function getTheOnlyOneInfo($criterion)
    // {
    //     $document = $this->call('getTheOnlyOne', $criterion);
    //     return $document['Content']['Info'];
    // }

    // final protected function getTheOnlyOneMeta($criterion)
    // {
    //     $document = $this->call('getTheOnlyOne', $criterion);
    //     return $document['Meta'];
    // }
    
}