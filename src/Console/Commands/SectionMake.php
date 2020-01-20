<?php

namespace SleepingOwl\Admin\Console\Commands;

use Illuminate\Console\GeneratorCommand as SectionGeneratorCommand;
use Illuminate\Database\Eloquent\Model;
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
     *
     * Свойства для генерируемых полей
     *
     * @var array
     */
    private $property;

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
                    $this->property = $cls->getProperty();
                } catch (\BadMethodCallException $e){
                    $this->property = [
                        'gridFields'=>null,
                        'editFields'=>null,
                        'modelTitle'=>null,
                        'menuIcon'=>null,
                        'enum'=>null,
                        'relations'=>null,
                    ];
                }

                foreach ($this->property as &$sectionProp){
                    if(is_array($sectionProp)){
                        foreach ($sectionProp as &$prop){
                            if(is_callable($prop)){
                                $prop = $prop($cls);
                            }
                        }
                    }elseif(is_callable($sectionProp)){
                        $sectionProp = $sectionProp($cls);
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
        $stub = $this->displayFiledGrid($stub);
        $stub = $this->editColumsForm($stub);
        $stub = $this->changeTitle($stub);
        $stub = $this->changeIcon($stub);

        return $stub;
    }

    /**
     * Собираем поля для отображения данных в гриде
     * @param array $attrs
     * @param $stub
     * @return string
     */
    protected function displayFiledGrid($stub):string
    {
        $attrEdits = [];
        $attrs= ['id'=>'int'];
        if(!empty($this->property['crud'])){
            $attrs = $this->property['crud']['gridFields'];
        }

        if($attrs){
            $max = 0;
            foreach ($attrs as $key=>$attr){
                if($max<=self::MAX_GRID_COLOMNS){
                    $attrEdits[] = "\AdminColumn::".$this->getInputByTypeGrid($attr)."('".$key."', '".$key."')";
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
    protected function editColumsForm($stub):string
    {
        $attrs = ['id'=>'int'];
        if(!empty($this->property['crud']) && !empty($this->property['crud']['editFields'])){
            $attrs = $this->property['crud']['editFields'];
        }

        $enum = [];
        if(!empty($this->property['enum'])){
            $enum = $this->property['enum'];
        }

        $relations = [];
        if(!empty($this->property['relations'])){
            $relations = $this->property['relations'];
        }

        $attrEdits = [];
        if($attrs){
            foreach ($attrs as $key=>$attr){
                if(isset($enum[$key])){
                    $attrEdits[] = "\AdminFormElement::select('".$key."', '".$key."', ".$this->arrayToStrinf($enum[$key])." )";
                } else{
                    $attrEdits[] = "\AdminFormElement::".$this->getInputByTypeForm($attr)."('".$key."', '".$key."')";
                }

            }
        }

        $stub = str_replace('DummyColumnsFieldsStr', implode(", \n\t\t\t\t", $attrEdits), $stub);
        $stub = str_replace('DummyColumnsFieldsArr', implode(", \n\t\t\t", $attrEdits), $stub);

        if($relations){
            foreach ($relations as $keyR=>$rel){
                $class = $rel['class'];
                try {
                    $relFileds = $class::fields(true);
                } catch (\Throwable $e) {
                    $relFileds = null;
                } catch (\Exception $e) {
                    $relFileds = null;
                }

                if($relFileds){
                    if($rel['relationOne']){
                        $attrEdits[] = "\AdminFormElement::hasMany('" . $keyR . "', ". $relFileds ." )->setLabel('" . $keyR . "')";
                    } else {
                        $attrEdits[] = "\AdminFormElement::belongsTo('" . $keyR . "', ". $relFileds ." )->setLabel('" . $keyR . "')";
                    }
                }
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
    protected function changeTitle($stub):string
    {
        $title = null;
        if(!empty($this->property['crud']) && !empty($this->property['crud']['modelTitle'])){
            $title = $this->property['crud']['modelTitle'];
        }

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
    protected function changeIcon($stub):string
    {
        $icon = null;
        if(!empty($this->property['crud']) && !empty($this->property['crud']['menuIcon'])){
            $icon = $this->property['crud']['menuIcon'];
        }

        if(empty($icon)){
            $icon = 'fa fa-lightbulb-o';
        }
        return str_replace('DummyModelIcon', $icon, $stub);
    }

    /**
     * Устанавливаем вид в зависимости от типа
     * @param string $type
     * @return string
     */
    protected function getInputByTypeForm(string $type)
    {
        switch ($type){
            case 'Carbon' : return 'datetime';
            case 'bool' : return 'checkbox';
            case 'array' : return 'checkbox';
            case 'int' : return 'number';
            default: return 'text';
        }
    }

    /**
     * Устанавливаем вид в зависимости от типа
     * @param string $type
     * @return string
     */
    protected function getInputByTypeGrid(string $type)
    {
        switch ($type){
            case 'Carbon' : return 'datetime';
            case 'bool' : return 'checkbox';
            case 'array' : return 'checkbox';
            default: return 'text';
        }
    }

    /**
     * @param array $ar
     * @return string
     */
    private function arrayToStrinf(array $ar): string
    {
        $s = '[';
        foreach ($ar as $key => $item) {
            if (is_array($item)) {
                $s .= "'" . $key . "'" . '=> ' . $this->arrayToStrinf($item);
            } else {
                $s .= "'" . $key . "'" . '=>' . "'" . $item . "',";
            }
        }
        $s .= ']';
        return $s;
    }
}
