<?php

namespace SleepingOwl\Admin\Console\Commands;

use Illuminate\Console\GeneratorCommand as SectionGeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;

class SectionMake extends SectionGeneratorCommand
{
    const MAX_GRID_COLOMNS = 5;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'sleepingowl:section:make';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new section class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Section';

    /**
     * Determine if the class already exists.
     *
     * @param string $rawName
     * @return bool
     */
    protected function alreadyExists($rawName)
    {
        return class_exists($rawName);
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__.'/stubs/section.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param string $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace;
    }

    /**
     * Replace the class name for the given stub.
     *
     * @param string $stub
     * @param string $name
     * @return string
     */
    protected function replaceClass($stub, $name)
    {
        $stub = parent::replaceClass($stub, $name);

        $stub = $this->generationFormModelClass($stub, $name);

        return str_replace('DummyModel', '\\'.trim((string) $this->argument('model'), '\\'), $stub);
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        $arguments = parent::getArguments();
        $arguments[] = ['model', InputArgument::REQUIRED, 'The name of the model class'];

        return $arguments;
    }

    /**
     * Обработка полученных данных из модели
     * @param $stub
     * @param $className
     * @return string
     */
    protected function generationFormModelClass($stub, $className):string
    {
        $attrEdits = [];
        $arguments = $this->argument();
        $crud = [
            'gridFields'=>null,
            'editFields'=>null,
            'modelTitle'=>null,
            'menuIcon'=>null,
        ];
        if(!empty($arguments['model'])){
            $classBase = $arguments['model'];
            try {
                $cls = new $classBase();
            } catch (\Throwable $e) {
                $cls = null;
            } catch (\Exception $e) {
                $cls = null;
            }


            if(!is_null($cls) ){
                try{
                    $property = $cls->getProperty();
                } catch (\BadMethodCallException $e){
                    $property = null;
                }

                if(!empty($property['crud'])) {
                    $crud = $property['crud'];
                    foreach ($crud as &$item){
                        if(is_callable($item)){
                            $item = $item($cls);
                        }
                    }
                }
            }
        }
        /*
        | Меняем отображаемые колонки в гриде
        | Настраиваем поля формы для редактирования
        | Устанавливаем заголовок
        | Устанавливаем иконку
        | Обрабатываем разрешения (реализовать)
        */
        $stub = $this->displayFiledGrid( ($crud['gridFields']??['id'=>'int']), $stub );
        $stub = $this->editColumsForm( ($crud['editFields']??['id'=>'int']), $stub );
        $stub = $this->changeTitle( ($crud['modelTitle']??null), $stub);
        $stub = $this->changeIcon(($crud['menuIcon']??null), $stub);

        return $stub;
    }

    /**
     * Собираем поля для отображения данных в гриде
     * @param array $attrs
     * @param $stub
     * @return string
     */
    protected function displayFiledGrid(array $attrs, $stub):string
    {
        $attrEdits = [];
        if($attrs){
            $max = 0;
            foreach ($attrs as $key=>$attr){
                if($max<=self::MAX_GRID_COLOMNS){
                    $attrEdits[] = "\AdminColumn::".$this->getInputByType($attr)."('".$key."', '".$key."')";
                } else {
                    break;
                }
                $max++;
            }
        }

        return str_replace('DummyColumnsDisplay', implode(", \n\t\t\t\t", $attrEdits), $stub);
    }

    /**
     * Собираем поля формы для редактирования/создания новых
     * Example:
     *      ColumnEdit    \AdminFormElement::text('name', 'Name')->required(),
     *
     * @param array $attrs
     * @param $stub
     * @return string
     */
    protected function editColumsForm(array $attrs, $stub):string
    {

        $attrEdits = [];
        if($attrs){
            foreach ($attrs as $key=>$attr){

                $attrEdits[] = "\AdminFormElement::".$this->getInputByType($attr)."('".$key."', '".$key."')";
            }
        }
        return str_replace('DummyColumnsEdit', implode(", \n\t\t\t", $attrEdits), $stub);

    }

    /**
     * Меняем заголовки
     * @param string|null $title
     * @param $stub
     * @return string
     */
    protected function changeTitle(string $title=null, $stub):string
    {
        if(!empty($title)){
            $titleReplace = "'" . $title . "'";
        } else {
            $titleReplace = 'null';
        }
        return str_replace('DummyModelTitle', $titleReplace, $stub);
    }


    /**
     * Меняем иконку
     * @param string|null $icon
     * @param $stub
     * @return string
     */
    protected function changeIcon(string $icon=null, $stub):string
    {
        if(empty($icon)){
            $titleReplace = 'fa fa-lightbulb-o';
        }
        return str_replace('DummyModelIcon', $icon, $stub);
    }

    /**
     * Устанавливаем вид в зависимости от типа
     * @param string $type
     * @return string
     */
    protected function getInputByType(string $type)
    {
        switch ($type){
            case 'Carbon' : return 'datetime';
            case 'bool' : return 'checkbox';
            default: return 'text';
        }
    }
}
