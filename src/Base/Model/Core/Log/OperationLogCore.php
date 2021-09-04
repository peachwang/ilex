<?php

namespace Ilex\Base\Model\Core\Log;

use \Ilex\Core\Context;
use \Ilex\Core\Loader;
use \Ilex\Lib\Kit;
use \Ilex\Base\Model\Core\BaseCore;

/**
 * Class OperationLogCore
 * @package Ilex\Base\Model\Core\Log
 */
final class OperationLogCore extends BaseCore
{
    const COLLECTION_NAME = 'OperationLog';
    const ENTITY_PATH     = 'Log/OperationLog';

    final public function addOperationLog($operation_type, $operation_context, $do_not_rollback = FALSE)
    {
        Kit::ensureString($operation_type);
        Kit::ensureArray($operation_context);
        $operation_log = $this->createEntity()
            ->setOperationType($operation_type)
            ->setOperationTime(Kit::now())
            ->setOperationContext($operation_context);
        $me = Context::me();
        if (FALSE === is_null($me)) $operation_log->setOperator($me);
        if (TRUE === $do_not_rollback) $operation_log->doNotRollback();
        return $operation_log->addToCollection();
    }
}