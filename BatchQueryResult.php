<?php
/**
 * Created by PhpStorm.
 * User: Brandon Tilstra
 * Date: 24-10-2018
 * Time: 15:07
 */

namespace promocat\rest;

class BatchQueryResult extends \yii\db\BatchQueryResult
{
    protected $initialPerPage;
    protected $initialLimit;
    protected $initialOffset;
    protected $response;
    protected $lastPage = false;
    protected $fetchedRows = 0;

    /**
     * @var array the data retrieved in the current batch
     */
    private $_batch;
    /**
     * @var mixed the value for the current iteration
     */
    private $_value;
    /**
     * @var string|int the key for the current iteration
     */
    private $_key;

    public function init()
    {
        $this->initialPerPage = $this->query->perPage;
        $this->initialLimit = $this->query->limit;
        $this->initialOffset = $this->query->offset;
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        $this->response = null;
        $this->lastPage = false;
        $this->fetchedRows = 0;

        $this->query->perPage($this->initialPerPage);
        $this->query->limit($this->initialLimit);
        $this->query->offset($this->initialOffset);

        $this->_batch = null;
        $this->_value = null;
        $this->_key = null;
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        if ($this->_batch === null || !$this->each || $this->each && next($this->_batch) === false) {

            if ($this->response !== null) { //a previous call was made, proceed to the next page
                $pageCount = (int)$this->response->headers->get($this->db->pageCountHeader);
                $currentPage = (int)$this->response->headers->get($this->db->currentPageHeader);
                $this->lastPage = $pageCount === $currentPage;

                if ($currentPage < $pageCount) { // We have not reached the end
                    $this->query->perPage((int)$this->response->headers->get($this->db->perPageHeaeder));
                    $this->query->offset($currentPage * $this->query->perPage);
                }
            }

            if ($this->initialLimit !== null && $this->fetchedRows >= $this->initialLimit) {
                $this->lastPage = true;
            }

            $this->_batch = $this->fetchData();
            if (!empty($this->_batch)) {
                $this->fetchedRows += count($this->_batch);
                if (!empty($this->initialLimit) && ($excessRows = $this->fetchedRows - $this->initialLimit) > 0) {
                    $this->_batch = array_slice($this->_batch, 0, -$excessRows);
                    $this->fetchedRows = $this->initialLimit;
                }
                reset($this->_batch);
            }
        }
        if ($this->each) {
            $this->_value = current($this->_batch);
            if ($this->query->indexBy !== null) {
                $this->_key = key($this->_batch);
            } elseif (key($this->_batch) !== null) {
                $this->_key = $this->_key === null ? 0 : $this->_key + 1;
            } else {
                $this->_key = null;
            }
        } else {
            $this->_value = $this->_batch;
            $this->_key = $this->_key === null ? 0 : $this->_key + 1;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchData()
    {
        if (!$this->lastPage) {
            $command = $this->query->createCommand($this->db);
            $rows = $command->queryAll();
            $this->response = $command->db->getResponse();
            return $this->query->populate($rows);
        }
        return [];
    }

    /**
     * Returns the index of the current dataset.
     * This method is required by the interface [[\Iterator]].
     * @return int the index of the current row.
     */
    public function key()
    {
        return $this->_key;
    }

    /**
     * Returns the current dataset.
     * This method is required by the interface [[\Iterator]].
     * @return mixed the current dataset.
     */
    public function current()
    {
        return $this->_value;
    }

    /**
     * Returns whether there is a valid dataset at the current position.
     * This method is required by the interface [[\Iterator]].
     * @return bool whether there is a valid dataset at the current position.
     */
    public function valid()
    {
        return !empty($this->_batch);
    }

}
