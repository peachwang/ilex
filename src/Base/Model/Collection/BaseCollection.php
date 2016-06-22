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
            // 'createEntity',
            // 'checkExistEntities',
            // 'checkExistsId',
            // 'checkExistsSignature',
            // 'countAll',
            // 'getTheOnlyOneEntityBySignature',
        ],
        self::V_PROTECTED => [
            // 'ensureExistEntities',
            // 'checkExistsOnlyOneEntity',
            // 'ensureExistsOnlyOneEntity',
            // 'countEntities',
            // 'getMultiEntities',
            // 'getTheOnlyOneEntity',
            // 'getOneEntity',
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

    final private function ensureInitialized()
    {
        if (FALSE === isset($this->collectionWrapper)
            OR FALSE === $this->collectionWrapper instanceof CollectionWrapper)
            throw new UserException('This collection has not been initialized.');
    }

    final public function createEntity()
    {
        $this->ensureInitialized();
        $entity_name       = $this->collectionWrapper->getEntityName();
        $entity_class_name = $this->collectionWrapper->getEntityClassName();
        $collection_name   = $this->collectionWrapper->getCollectionName();
        $entity_wrapper    = EntityWrapper::getInstance($collection_name, $entity_class_name);
        return new $entity_class_name($entity_wrapper, $entity_name, FALSE);
    }

    final public function checkExistsId($_id)
    {
        $criterion = [ '_id' => $_id ];
        return $this->checkExistsOnlyOneEntity($criterion);
    }

    final public function checkExistsSignature($signature)
    {
        $criterion = [ 'Signature' => $signature ];
        return $this->checkExistsOnlyOneEntity($criterion);
    }

    final public function countAll()
    {
        return $this->countEntities();
    }
    
    final public function getTheOnlyOneEntityBySignature($signature)
    {
        $criterion = [
            'Signature' => $signature,
        ];
        return $this->getTheOnlyOneEntity($criterion);
    }


    //==============================================================================


    final protected function checkExistEntities($criterion)
    {
        $this->ensureInitialized();
        return $this->collectionWrapper->checkExistEntities($criterion);
    }

    final protected function ensureExistEntities($criterion)
    {
        $this->ensureInitialized();
        $this->collectionWrapper->ensureExistEntities($criterion);
    }

    final protected function checkExistsOnlyOneEntity($criterion)
    {
        $this->ensureInitialized();
        return $this->collectionWrapper->checkExistsOnlyOneEntity($criterion);
    }

    final protected function ensureExistsOnlyOneEntity($criterion)
    {
        $this->ensureInitialized();
        $this->collectionWrapper->ensureExistsOnlyOneEntity($criterion);
    }
     
    final protected function countEntities($criterion = [], $skip = NULL, $limit = NULL)
    {
        $this->ensureInitialized();
        return $this->collectionWrapper->countEntities($criterion, $skip, $limit);
    }

    final protected function getMultiEntities($criterion, $sort_by = NULL, $skip = NULL, $limit = NULL)
    {
        $this->ensureInitialized();
        return $this->collectionWrapper->getMultiEntities($criterion, $sort_by, $skip, $limit);
    }

    final protected function getTheOnlyOneEntity($criterion)
    {
        $this->ensureInitialized();
        return $this->collectionWrapper->getTheOnlyOneEntity($criterion);
    }

    final protected function getOneEntity($criterion, $sort_by = NULL, $skip = NULL, $limit = NULL)
    {
        $this->ensureInitialized();
        return $this->collectionWrapper->getOneEntity($criterion, $sort_by, $skip, $limit);
    }
}