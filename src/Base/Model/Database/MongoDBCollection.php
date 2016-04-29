<?php

namespace Ilex\Base\Model\Database;

use \Ilex\Base\Model\Base;
use \Ilex\Core\Loader;

/**
 * Class MongoDBCollection
 * Encapsulation of basic operations of MongoDB collections.
 * @package Ilex\Base\Model\Database
 *
 * @property protected \MongoCollection $collection
 * @property protected string $collectionName
 * 
 * @method protected                    initialize()
 * @method protected array|\MongoCursor find(array $criterion = [], array $projection = [], boolean $toArray = TRUE)
 * @method protected boolean insert(array $document)
 *
 * @method private mixed getId(mixed $id)
 * @method private array setRetractId(array $data)
 */
class MongoDBCollection extends Base
{
    // protected $collectionName;
    
    private $collection;

    // protected function initialize($collectionName)
    public function initialize($collectionName)
    {
        echo '<br>'.__METHOD__.$collectionName.'789<br>';
        // $this->collection = Loader::db()->selectCollection($this->collectionName);
        $this->collection = Loader::db()->selectCollection($collectionName);
        echo '<br>'.__METHOD__.$collectionName.'999<br>';
    }

    /**
     * @todo: protected
     * @param array   $criterion
     * @param array   $projection
     * @param boolean $toArray
     * @return array|\MongoCursor
     */
    public function find($criterion = [], $projection = [], $toArray = TRUE)
    {
        $criterion = $this->setRetractId($criterion);
        $cursor = $this->collection->find($criterion, $projection);
        return $toArray ? array_values(iterator_to_array($cursor)) : $cursor;
    }

    /**
     * @todo: protected
     * @param array $document
     * @return boolean
     */
    public function insert($document)
    {
        if (!isset($document['Meta'])) $document['Meta'] = [];
        $document['Meta']['CreateTime'] = new \MongoDate(time());
        try {
            $this->collection->insert($document, ['w' => 1]);
            return TRUE;
        } catch(MongoCursorException $e) {
            return FALSE;
        }
    }

    /**
     * Normalizes $id.
     * @param mixed $id
     * @return mixed
     */
    private function getId($id)
    {
        if (is_string($id)) {
            try {
                return new \MongoId($id);
            } catch (\Exception $e) {
                return $id;
            }
        } else {
            return $id;
        }
    }

    /**
     * Normalizes _id in $data.
     * @param array $data
     * @return array
     */
    private function setRetractId($data)
    {
        if (isset($data['_id'])) {
            $data['_id'] = $this->getId($data['_id']);
        }
        return $data;
    }
}
