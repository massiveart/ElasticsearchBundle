<?php

/*
 * This file is part of the ONGR package.
 *
 * (c) NFQ Technologies UAB <info@nfq.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ONGR\ElasticsearchBundle\Service;

use ONGR\ElasticsearchBundle\Result\RawIterator;
use ONGR\ElasticsearchBundle\Result\Result;
use ONGR\ElasticsearchDSL\Query\QueryStringQuery;
use ONGR\ElasticsearchDSL\Search;
use ONGR\ElasticsearchDSL\Sort\FieldSort;
use ONGR\ElasticsearchBundle\Result\DocumentIterator;

/**
 * Document repository class.
 */
class Repository
{
    /**
     * @var Manager
     */
    private $manager;

    /**
     * @var string Fully qualified class name
     */
    private $className;

    /**
     * @var string Elasticsearch type name
     */
    private $type;

    /**
     * Constructor.
     *
     * @param Manager $manager
     * @param string  $className
     */
    public function __construct($manager, $className)
    {
        if (!is_string($className)) {
            throw new \InvalidArgumentException('Class name must be a string.');
        }

        if (!class_exists($className)) {
            throw new \InvalidArgumentException(
                sprintf('Cannot create repository for non-existing class "%s".', $className)
            );
        }

        $this->manager = $manager;
        $this->className = $className;
        $this->type = $this->resolveType($className);
    }

    /**
     * Returns elasticsearch manager used in the repository.
     *
     * @return Manager
     */
    public function getManager()
    {
        return $this->manager;
    }

    /**
     * @return array
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Returns a single document data by ID or null if document is not found.
     *
     * @param string $id Document ID to find
     *
     * @return object
     */
    public function find($id)
    {
        return $this->manager->find($this->type, $id);
    }

    /**
     * Finds documents by a set of criteria.
     *
     * @param array      $criteria   Example: ['group' => ['best', 'worst'], 'job' => 'medic'].
     * @param array|null $orderBy    Example: ['name' => 'ASC', 'surname' => 'DESC'].
     * @param int|null   $limit      Example: 5.
     * @param int|null   $offset     Example: 30.
     *
     * @return array|DocumentIterator The objects.
     */
    public function findBy(
        array $criteria,
        array $orderBy = [],
        $limit = null,
        $offset = null
    ) {
        $search = $this->createSearch();

        if ($limit !== null) {
            $search->setSize($limit);
        }
        if ($offset !== null) {
            $search->setFrom($offset);
        }

        foreach ($criteria as $field => $value) {
            $search->addQuery(
                new QueryStringQuery(is_array($value) ? implode(' OR ', $value) : $value, ['default_field' => $field])
            );
        }

        foreach ($orderBy as $field => $direction) {
            $search->addSort(new FieldSort($field, $direction));
        }

        return $this->execute($search);
    }

    /**
     * Finds a single document by a set of criteria.
     *
     * @param array      $criteria   Example: ['group' => ['best', 'worst'], 'job' => 'medic'].
     * @param array|null $orderBy    Example: ['name' => 'ASC', 'surname' => 'DESC'].
     *
     * @return object|null The object.
     */
    public function findOneBy(array $criteria, array $orderBy = [])
    {
        $result = $this->findBy($criteria, $orderBy, null, null);

        return $result->first();
    }

    /**
     * Returns search instance.
     *
     * @return Search
     */
    public function createSearch()
    {
        return new Search();
    }

    /**
     * Executes given search.
     *
     * @param Search $search
     * @param string $resultsType
     *
     * @return DocumentIterator|RawIterator|array
     *
     * @throws \Exception
     */
    public function execute(Search $search, $resultsType = Result::RESULTS_OBJECT)
    {
        return $this->manager->execute([$this->type], $search, $resultsType);
    }

    /**
     * Counts documents by given search.
     *
     * @param Search $search
     * @param array  $params
     * @param bool   $returnRaw If set true returns raw response gotten from client.
     *
     * @return int|array
     */
    public function count(Search $search, array $params = [], $returnRaw = false)
    {
        $body = array_merge(
            [
                'index' => $this->getManager()->getIndexName(),
                'type' => $this->type,
                'body' => $search->toArray(),
            ],
            $params
        );

        $results = $this
            ->getManager()
            ->getClient()->count($body);

        if ($returnRaw) {
            return $results;
        } else {
            return $results['count'];
        }
    }

    /**
     * Removes a single document data by ID.
     *
     * @param string $id Document ID to remove.
     *
     * @return array
     *
     * @throws \LogicException
     */
    public function remove($id)
    {
        $params = [
            'index' => $this->getManager()->getIndexName(),
            'type' => $this->type,
            'id' => $id,
        ];

        $response = $this->getManager()->getClient()->delete($params);

        return $response;
    }

    /**
     * Partial document update.
     *
     * @param string $id     Document id to update.
     * @param array  $fields Fields array to update.
     * @param string $script Groovy script to update fields.
     * @param array  $params Additional parameters to pass to the client.
     *
     * @return array
     */
    public function update($id, array $fields = [], $script = null, array $params = [])
    {
        $body = array_filter(
            [
                'doc' => $fields,
                'script' => $script,
            ]
        );

        $params = array_merge(
            [
                'id' => $id,
                'index' => $this->getManager()->getIndexName(),
                'type' => $this->type,
                'body' => $body,
            ],
            $params
        );

        return $this->getManager()->getClient()->update($params);
    }

    /**
     * Resolves elasticsearch type by class name.
     *
     * @param string $className
     *
     * @return array
     */
    private function resolveType($className)
    {
        return $this->getManager()->getMetadataCollector()->getDocumentType($className);
    }

    /**
     * Returns fully qualified class name.
     *
     * @return string
     */
    public function getClassName()
    {
        return $this->className;
    }
}
