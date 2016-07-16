<?php

namespace Ilex\Base\Model\Entity\User;

use \MongoDate;
use \Ilex\Core\Context as c;
use \Ilex\Lib\Kit;
use \Ilex\Lib\MongoDB\MongoDBDate;
use \Ilex\Base\Model\Entity\BaseEntity;

/**
 * Class UserEntity
 * @package Ilex\Base\Model\Entity\User
 */
class UserEntity extends BaseEntity
{

    public function setUsername($username)
    {
        Kit::ensureString($username);
        $this->setInfo('Username', $username);
        return $this;
    }

    final public function getUsername()
    {
        return $this->getInfo('Username');
    }

    public function setPassword($password)
    {
        Kit::ensureString($password);
        $this->setInfo('Password', $password);
        return $this;
    }

    final public function getPassword()
    {
        return $this->getInfo('Password');
    }

    public function setEmail($email)
    {
        // @TODO: validate
        Kit::ensureString($email);
        $this->setInfo('Email', $email);
        return $this;
    }

    final public function getEmail()
    {
        return $this->getInfo('Email');
    }

    final public function loginNow()
    {
        return $this->setInfo('LastLoginTime', MongoDBDate::now());
    }

    final public function getLastLoginTimestamp()
    {
        return MongoDBDate::toTimestamp($this->getInfo('LastLoginTime'));
    }

    public function getAbstract()
    {
        return [
            'Id'   => $this->getId(TRUE),
            'Name' => $this->getName(),
            'Type' => $this->getType(),
        ];
    }

    final public function getDetail()
    {
        return $this->getAbstract() + [
            'Username'              => $this->getUsername(),
            'Email'                 => $this->getEmail(),
            'RegistrationTimestamp' => $this->getCreationTimestamp() * 1000,
            'LastLoginTimestamp'    => $this->getLastLoginTimestamp() * 1000,
        ];
    }

    final public function isMe()
    {
        return $this->getId()->isEqualTo(c::user()->getId());
    }

    final public function ensureMe()
    {
        if (FALSE === $this->isMe())
            throw new UserException('This user is not me.');
        return $this;
    }

}