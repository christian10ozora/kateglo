<?php
namespace kateglo\proxies {
    /**
     * THIS CLASS WAS GENERATED BY THE DOCTRINE ORM. DO NOT EDIT THIS FILE.
     */
    class kategloapplicationmodelsRelationTypeAProxy extends \kateglo\application\models\RelationType implements \Doctrine\ORM\Proxy\Proxy {
        private $_em;
        private $_assoc;
        private $_owner;
        private $_joinColumnValues;
        private $_loaded = false;
        public function __construct($em, $assoc, $owner, array $joinColumnValues) {
            $this->_em = $em;
            $this->_assoc = $assoc;
            $this->_owner = $owner;
            $this->_joinColumnValues = $joinColumnValues;
            
        }
        private function _load() {
            if ( ! $this->_loaded) {
                $this->_assoc->load($this->_owner, $this, $this->_em, $this->_joinColumnValues);
                unset($this->_em);
                unset($this->_owner);
                unset($this->_assoc);
                unset($this->_joinColumnValues);
                $this->_loaded = true;echo "load false ";
            }else{echo "load true ";}
        }
        public function __isInitialized__() { return $this->_loaded; }

        
        public function setId($id) {
            $this->_load();
            return parent::setId($id);
        }

        public function getId() {
            $this->_load();
            return parent::getId();
        }

        public function setType($type) {
            $this->_load();
            return parent::setType($type);
        }

        public function getType() {
            $this->_load();
            return parent::getType();
        }

        public function setAbbreviation($abbreviation) {
            $this->_load();
            return parent::setAbbreviation($abbreviation);
        }

        public function getAbbreviation() {
            $this->_load();
            return parent::getAbbreviation();
        }

        public function addRelation(\kateglo\application\models\Relation $relation) {
            $this->_load();
            return parent::addRelation($relation);
        }

        public function removeRelation(\kateglo\application\models\Relation $relation) {
            $this->_load();
            return parent::removeRelation($relation);
        }

        public function getRelations() {
            $this->_load();
            return parent::getRelations();
        }


        public function __sleep() {
            if (!$this->_loaded) {
                throw new \RuntimeException("Not fully loaded proxy can not be serialized.");
            }
            return array('id', 'type', 'abbreviation', 'relations');
        }
    }
}