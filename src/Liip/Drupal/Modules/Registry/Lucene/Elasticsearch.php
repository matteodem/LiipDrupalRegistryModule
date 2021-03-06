<?php
namespace Liip\Drupal\Modules\Registry\Lucene;

use Assert\Assertion;
use Assert\InvalidArgumentException;
use Elastica\Exception\NotFoundException;
use Liip\Drupal\Modules\DrupalConnector\Common;
use Liip\Drupal\Modules\Registry\Adaptor\ElasticaAdaptor;
use Liip\Drupal\Modules\Registry\Registry;
use Liip\Drupal\Modules\Registry\RegistryException;
use Elastica\Client;


class Elasticsearch extends Registry
{
    /**
     * @var \Elastica\Client Instance of the elasticsearch library.
     */
    protected $elasticaClient;

    /**
     * @var \Elastica\Index[]
     */
    protected $registry;

    /**
     * @var \Liip\Drupal\Modules\Registry\Adaptor\ElasticaAdaptor
     */
    protected $adaptor;


    /**
     * @param string $section
     * @param \Liip\Drupal\Modules\DrupalConnector\Common $dcc
     * @param \Assert\Assertion $assertion
     */
    public function __construct($section, Common $dcc, Assertion $assertion)
    {
        $this->validateElasticaDependency();

        parent::__construct($section, $dcc, $assertion);

        $this->init();
    }

    /**
     * Initates a registry.
     *
     * @throws \Liip\Drupal\Modules\Registry\RegistryException in case the initiation of an active registry was requested.
     */
    public function init()
    {
        // elastica will complain if the index name is not lowercase.
        $this->section = strtolower($this->section);

        if(! empty($this->registry[$this->section])) {

            throw new RegistryException(
                $this->drupalCommonConnector->t(
                    RegistryException::DUPLICATE_INITIATION_ATTEMPT_TEXT . '(@section)',
                    array('@section' => $this->section)
                ),
                RegistryException::DUPLICATE_INITIATION_ATTEMPT_CODE
            );
        }

        $this->adaptor = new ElasticaAdaptor();
        $this->registry[$this->section] = $this->adaptor->getIndex($this->section);
    }

    /**
     * Adds an item to the register.
     *
     * @param string $identifier
     * @param mixed $value
     *
     * @throws \Liip\Drupal\Modules\Registry\RegistryException
     */
    public function register($identifier, $value)
    {
        if ($this->isRegistered($identifier)) {
            throw new RegistryException(
                $this->drupalCommonConnector->t(RegistryException::DUPLICATE_REGISTRATION_ATTEMPT_TEXT),
                RegistryException::DUPLICATE_REGISTRATION_ATTEMPT_CODE
            );
        }

        $this->adaptor->registerDocument($this->section, $value, $identifier);
    }

    /**
     * Replaces the content of the item identified by it's registration key by the new value.
     *
     * @param string $identifier
     * @param mixed $value
     *
     * @throws \Liip\Drupal\Modules\Registry\RegistryException
     */
    public function replace($identifier, $value)
    {
        if (!$this->isRegistered($identifier)) {
            throw new RegistryException(
                $this->drupalCommonConnector->t(RegistryException::MODIFICATION_ATTEMPT_FAILED_TEXT),
                RegistryException::MODIFICATION_ATTEMPT_FAILED_CODE
            );
        }

        $this->adaptor->updateDocument($identifier, array('doc' => $value), $this->section);
    }

    /**
     * Removes an item off the register.
     *
     * @param string $identifier
     *
     * @throws \Liip\Drupal\Modules\Registry\RegistryException
     */
    public function unregister($identifier)
    {
        if (!$this->isRegistered($identifier)) {
            throw new RegistryException(
                $this->drupalCommonConnector->t(RegistryException::UNKNOWN_IDENTIFIER_TEXT),
                RegistryException::UNKNOWN_IDENTIFIER_CODE
            );
        }

        $this->adaptor->removeDocuments(array($identifier), $this->section);
    }

    /**
     * Shall delete the current registry from the database.
     */
    public function destroy()
    {
        $this->registry = array();

        $this->adaptor->deleteIndex($this->section);
    }

    /**
     * Verifies the existence of the
     *
     * @throws \Liip\Drupal\Modules\Registry\RegistryException
     */
    protected function validateElasticaDependency()
    {
        if (!class_exists('\Elastica\Index')) {

            throw new RegistryException(
                RegistryException::MISSING_DEPENDENCY_TEXT,
                RegistryException::MISSING_DEPENDENCY_CODE
            );
        }
    }

    /**
     * Verifies a document is in the elasticsearch index.
     *
     * @param string $identifier
     *
     * @return bool
     */
    public function isRegistered($identifier)
    {
        try {
           $this->adaptor->getDocument($identifier, $this->section);

        } catch (NotFoundException $e) {

            return false;
        }

        return true;
    }
}
