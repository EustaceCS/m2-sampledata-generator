<?php

namespace Atwix\Samplegen\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Magento\Framework\App\ObjectManagerFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManager;
use \Atwix\Samplegen\Model\EntityGeneratorContext as Context;

/**
 * Command for executing cron jobs
 */
class GenerateCategoriesCommand extends Command
{
    const JOB_NAME = 'samplegen:generate:categories';
    const INPUT_KEY_COUNT = 'count';
    const INPUT_KEY_DEPTH = 'depth';
    const INPUT_KEY_REMOVE = 'remove';

    /**
     * Object manager factory
     *
     * @var ObjectManagerFactory
     */
    private $objectManagerFactory;

    protected $context;

    /**
     * Constructor
     *
     * @param ObjectManagerFactory $objectManagerFactory
     * @param Context $context
     */
    public function __construct(ObjectManagerFactory $objectManagerFactory, Context $context)
    {
        $this->objectManagerFactory = $objectManagerFactory;
        $this->context = $context;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $options = [
            new InputOption(
                self::INPUT_KEY_COUNT,
                null,
                InputOption::VALUE_REQUIRED,
                'Generated categories count'
            ),
            new InputOption(
                self::INPUT_KEY_DEPTH,
                null,
                InputOption::VALUE_OPTIONAL,
                'Generated categories depth',
                0
            ),
            new InputOption(
                self::INPUT_KEY_REMOVE,
                null,
                InputOption::VALUE_OPTIONAL,
                'Remove all previously generated categories',
                false
            )
        ];

        $this->setName(self::JOB_NAME)
            ->setDescription('Runs sample categories generation')
            ->setDefinition($options);
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $categoriesCount = $input->getOption(self::INPUT_KEY_COUNT);
        $removeGeneratedCategories = $input->getOption(self::INPUT_KEY_REMOVE);
        $messages = $this->validate($categoriesCount, $removeGeneratedCategories);

        if (!empty($messages)) {
            $output->writeln(implode(PHP_EOL, $messages));
            return;
        }

        $omParams = $_SERVER;
        $omParams[StoreManager::PARAM_RUN_CODE] = 'admin';
        $omParams[Store::CUSTOM_ENTRY_POINT_PARAM] = true;
        $objectManager = $this->objectManagerFactory->create($omParams);

        $params[self::INPUT_KEY_COUNT] = $categoriesCount;
        $params[self::INPUT_KEY_DEPTH] = $input->getOption(self::INPUT_KEY_DEPTH);
        $params[self::INPUT_KEY_REMOVE] = $removeGeneratedCategories;

        $adminAppState = $objectManager->get('Magento\Framework\App\State');
        $adminAppState->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);

        $this->context->setParameters($params);
        $this->context->setObjectManager($objectManager);

        $categoriesCreator = $objectManager->create('Atwix\Samplegen\Helper\CategoriesCreator',
            ['context' => $this->context]);
        try {
            $categoriesCreator->launch();
            $output->writeln('<info>' . 'All operations completed successfully' . '</info>');
        } catch (\Exception $e) {
            $output->writeln('<error>' . "{$e->getMessage()}" . '</error>');
        }
    }

    /**
     * Validates categories count and returns error messages
     *
     * @param $count
     * @return array
     */
    protected function validate($count, $removeAll)
    {
        // TODO: check count for negative values
        $messages = [];
        if (false == $count && false == $removeAll) {
            $messages[] = '<error>No categories count specified</error>';
        }

        return $messages;
    }
}
