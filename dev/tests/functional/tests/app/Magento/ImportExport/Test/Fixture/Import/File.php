<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\ImportExport\Test\Fixture\Import;

use Magento\ImportExport\Mtf\Util\Import\File\CsvTemplate;
use Magento\Mtf\Fixture\DataSource;
use Magento\Mtf\Fixture\FixtureFactory;
use Magento\Mtf\Fixture\FixtureInterface;
use Magento\Mtf\ObjectManager;
use Magento\Mtf\Util\Generate\File\Generator;

/**
 * Fixture of file.
 */
class File extends DataSource
{
    /**
     * Fixture data.
     *
     * @var array
     */
    private $value;

    /**
     * Template of csv file.
     *
     * @var array
     */
    private $csvTemplate;

    /**
     * Generator for the uploaded file.
     *
     * @var Generator
     */
    private $generator;

    /**
     * Factory for fixtures.
     *
     * @var FixtureFactory
     */
    private $fixtureFactory;

    /**
     * Entities fixtures.
     *
     * @var FixtureInterface[]
     */
    private $entities;

    /**
     * Object manager.
     *
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * Csv as array.
     *
     * @var array
     */
    private $csv;

    /**
     * @param ObjectManager $objectManager
     * @param FixtureFactory $fixtureFactory
     * @param Generator $generator
     * @param array $params
     * @param array|string $data
     */
    public function __construct(
        ObjectManager $objectManager,
        FixtureFactory $fixtureFactory,
        Generator $generator,
        array $params,
        $data = []
    ) {
        $this->params = $params;
        $this->value = $data;
        $this->generator = $generator;
        $this->fixtureFactory = $fixtureFactory;
        $this->objectManager = $objectManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getData($key = null)
    {
        if (isset($this->data)) {
            return parent::getData($key);
        }

        $filename = MTF_TESTS_PATH . $this->value['template']['filename'] . '.php';
        if (!file_exists($filename)) {
            throw new \Exception("CSV file '{$filename}'' not found on the server.");
        }

        $this->csvTemplate = include $filename;

        $placeholders = [];
        if (!isset($this->products)
            && isset($this->value['entities'])
            && is_array($this->value['entities'])
        ) {
            $this->entities = $this->createEntities();

            $placeholders = $this->getPlaceHolders();
        }

        if (isset($this->value['template']) && is_array($this->value['template'])) {
            $csvTemplate = $this->objectManager->create(
                CsvTemplate::class,
                [
                    'config' => array_merge(
                        $this->value['template'],
                        [
                            'placeholders' => $placeholders
                        ]
                    )
                ]
            );
            $this->data = $this->generator->generate($csvTemplate);
            $this->csv = $csvTemplate->getCsv();

            return parent::getData($key);
        }

        $filename = MTF_TESTS_PATH . $this->value;
        if (!file_exists($filename)) {
            throw new \Exception("CSV file '{$filename}'' not found on the server.");
        }

        $this->data = $filename;

        return parent::getData($key);
    }

    /**
     * Get entities fixtures.
     *
     * @return FixtureInterface[]
     */
    public function getEntities()
    {
        return $this->entities ?: [];
    }

    /**
     * Get fixture data.
     *
     * @return array|null
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Create products from configuration of variation.
     *
     * @return FixtureInterface[]
     */
    private function createEntities()
    {
        $entities = [];
        foreach ($this->value['entities'] as $key => $productDataSet) {
            list($fixtureCode, $dataset) = explode('::', $productDataSet);

            /** @var FixtureInterface[] $products */
            $entities[$key] = $this->fixtureFactory->createByCode(trim($fixtureCode), ['dataset' => trim($dataset)]);
            if ($entities[$key]->hasData('id') === false) {
                $entities[$key]->persist();
            }
        }

        return $entities;
    }

    /**
     * Create placeholders for products.
     *
     * @return array
     */
    private function getPlaceHolders()
    {
        $key = 0;
        foreach ($this->entities as $entity) {
            $entityData = $entity->getData();
            $entityData['code'] = $entity->getDataFieldConfig('website_ids')['source']->getWebsites()[0]->getCode();
            foreach ($this->csvTemplate['entity_' . $key] as $tierKey => $tier) {
                $values = implode('', array_values($tier));
                preg_match_all('/\%(.*)\%/U', $values, $indexes);

                foreach ($indexes[1] as $index) {
                    if (isset($entityData[$index])) {
                        $placeholders['entity_' . $key][$tierKey]["%{$index}%"] = $entityData[$index];
                    }
                }

            }
            $key++;
        }

        return $placeholders;
    }

    /**
     * Return csv as array.
     *
     * @return string
     */
    public function getCsv()
    {
        return $this->csv;
    }

    /**
     * Return csv as array.
     *
     * @param $csv
     * @return void
     */
    public function setCsv($csv)
    {
        $this->csv = $csv;
    }
}
