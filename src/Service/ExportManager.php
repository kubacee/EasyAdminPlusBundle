<?php

namespace Lle\EasyAdminPlusBundle\Service;

use Doctrine\Common\Collections\Collection;
use Lle\EasyAdminPlusBundle\Service\Exporter\ExporterInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use EasyCorp\Bundle\EasyAdminBundle\Configuration\ConfigManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\DataCollectorTranslator;
use Symfony\Component\Translation\TranslatorInterface;
use Twig\Environment as Twig;

class ExportManager
{

    /** @var PropertyAccessor */
    private $propertyAccessor;
    private $configManager;
    private $translator;
    /** @var Twig  */
    private $twig;
    private $exporters = [];


    public function __construct(
        ConfigManager $configManager,
        PropertyAccessor $propertyAccessor,
        TranslatorInterface $translator,
        iterable $exporters,
        Twig $twig
    ) {
        $this->configManager = $configManager;
        $this->propertyAccessor = $propertyAccessor;
        $this->translator = $translator;
        foreach($exporters as $exporter){
            if($exporter instanceof ExporterInterface) {
                if (array_key_exists($exporter->getFormat(), $this->exporters)) {
                    throw new \Exception('The exporter format ' . $exporter->getFormat() . ' already exist');
                }
                $this->exporters[$exporter->getFormat()] = $exporter;
            }
        }

        $this->twig = $twig;
    }

    private function getExportableValue($entity, array $field): ?string{
        $template = $field['template'];

        $value = $this->propertyAccessor->getValue($entity, $field['property']);

        if ($value instanceOf \DateTime){
            $value = $value->format($field['format']);
        }
        elseif(is_array($value)){
            $value = implode(',', $value);
        }
        elseif ($value instanceof Collection) {
            // @todo, quick fix
            if ($template) {
                return $this->twig->render($template, ['value' => $value]);
            }
        }
        elseif(is_object($value)){
            $value = (string)$value;
        }

        return $value;
    }

    private function generateData(iterable $paginator, array $fields): array{
        $data= [];
        $keys = array_keys($fields);
        for ($i = 0, $count = count($keys); $i < $count; $i++) {
            $data[0][$i] = $this->translator->trans($fields[$keys[$i]]['label'] ?? $keys[$i]);
        }
        $i = 1;
        foreach ($paginator as $entity) {
            foreach ($fields as $field) {
                $data[$i][] = $this->getExportableValue($entity, $field);
            }
            $i++;
        }
        return $data;
    }

    public function getExporter($format): ExporterInterface{
        if(array_key_exists($format, $this->exporters)){
            return $this->exporters[$format];
        }else{
            throw new \Exception('The format '. $format .' is not found');
        }
    }

    public function generateResponse(iterable $paginator, array $fields, string $filename,  string $format = 'csv'): Response{
        $data = $this->generateData($paginator, $fields);
        return $this->getExporter($format)->generateResponse($data, $filename);
    }


}
