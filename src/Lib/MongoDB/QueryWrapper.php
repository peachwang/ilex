<?php

namespace Ilex\Lib\MongoDB;

use \MongoId;
use \Ilex\Core\Loader;
use \Ilex\Lib\Container;
use \Ilex\Lib\Kit;
use \Ilex\Lib\UserException;

/**
 * Class QueryWrapper
 * @package Ilex\Lib\MongoDB
 */
final class QueryWrapper extends MongoDBCollection
{
    
    private static $queryWrapperContainer = NULL;

    private $entityPath          = NULL;
    private $entityClassName     = NULL;
    private $entityBulkClassName = NULL;

    final public static function getInstance($collection_name, $entity_path)
    {
        Kit::ensureString($entity_path);
        if (FALSE === isset(self::$queryWrapperContainer))
            self::$queryWrapperContainer = new Container();
        if (TRUE === self::$queryWrapperContainer->has($entity_path)) 
            return self::$queryWrapperContainer->get($entity_path);
        else return (self::$queryWrapperContainer->set(
            $entity_path, new static($collection_name, $entity_path)));
    }

    final protected function __construct($collection_name, $entity_path)
    {
        // @TODO: check when $collection_name and $entity_path can be null
        parent::__construct($collection_name);
        $this->entityPath = $entity_path;
        if (TRUE === is_null($entity_path))
            throw new UserException('ENTITY_PATH is not set.'); // @CAUTION
        $this->includeEntity();
        $this->includeEntityBulk();
    }

    final private function includeEntity()
    {
        $this->entityClassName = Loader::includeEntity($this->entityPath);
    }

    final private function includeEntityBulk()
    {
        $this->entityBulkClassName = Loader::includeEntityBulk($this->entityPath);
    }

    //===============================================================================================

    final public function checkExistEntities($criterion)
    {
        return $this->checkExistence($criterion);
    }

    final public function ensureExistEntities($criterion)
    {
        $this->ensureExistence($criterion);
    }

    final public function checkExistsOnlyOneEntity($criterion)
    {
        return $this->checkExistsOnlyOnce($criterion);
    }

    final public function ensureExistsOnlyOneEntity($criterion)
    {
        $this->ensureExistsOnlyOnce($criterion);
    }
     
    final public function countEntities($criterion = [], $skip = NULL, $limit = NULL)
    {
        return $this->count($criterion, $skip, $limit);
    }

    final public function getMultiEntities($criterion, $sort_by = NULL, $skip = NULL, $limit = NULL, $timeout = NULL)
    {
        $cursor = $this->getMulti($criterion, [ ], $sort_by, $skip, $limit, FALSE, $timeout);
        $entity_bulk_class_name = Kit::ensureString($this->entityBulkClassName);
        return new $entity_bulk_class_name($cursor,
            $this->collectionName, $this->entityPath, $this->entityClassName);
    }

    final public function getTheOnlyOneEntity($criterion)
    {
        $document = $this->getTheOnlyOne($criterion);
        return $this->createEntityWithDocument($document);
    }

    final public function getOneEntity($criterion, $sort_by = NULL, $skip = NULL, $limit = NULL)
    {
        $document = $this->getOne($criterion, [ ], $sort_by, $skip, $limit);
        return $this->createEntityWithDocument($document);
    }

    final public function removeMultiEntities($criterion, $timeout = NULL)
    {
        // $cursor = $this->getMulti($criterion, [ '_id' => 1 ], $timeout);
        $status = $this->removeMulti($criterion, $timeout);
        return $status;
    }

    //===============================================================================================

    final private function createEntityWithDocument($document)
    {
        // Kit::ensureDict($document); // @CAUTION
        Kit::ensureArray($document);
        if (FALSE === isset($document['_id']) OR FALSE === $document['_id'] instanceof MongoId)
            throw new UserException('_id is not set or proper in $document.', $document);
        $document['_id']   = new MongoDBId($document['_id']);
        $entity_class_name = Kit::ensureString($this->entityClassName);
        return new $entity_class_name($this->collectionName, $this->entityPath, TRUE, $document);
    }
}