<?php

namespace Biswadeep\FormTool\Http\Libraries;

use Closure;

class DataModel
{
    private $dataTypeList = [];
    public $actions = [];

    public $form = null;

    public bool $isMultiple = false;
    public $label = '';
    private $key = '';
    private $subDataModel = [];
    private $parentDataModel = null;

    private $multipleRequired = 0;
    private $isMultipleConfirmBeforeDelete = false;
    private $isMultipleSortable = false;
    private $multipleSortableField = '';
    //private $isMultipleKeepId = false;

    private $multipleModel = null;
    private $multipleTable = null;

    public function __construct($key = '', $isMultiple = false, $parentDataModel = null)
    {
        $this->key = $key;
        $this->isMultiple = $isMultiple;
        $this->parentDataModel = $parentDataModel;
    }

    public function getList() : array
    {
        return $this->_dataTypeList;
    }

    public function text(string $dbField, string $label = null) : InputTypes\TextType
    {
        $inputType = new InputTypes\TextType();
        $inputType->init($this, $dbField, $label);
        $this->_dataTypeList[] = $inputType;

        return $inputType;
    }

    public function hidden(string $dbField, string $label = null) : InputTypes\HiddenType
    {
        $inputType = new InputTypes\HiddenType();
        $inputType->init($this, $dbField, $label);
        $this->_dataTypeList[] = $inputType;

        return $inputType;
    }

    public function file(string $dbField, string $label = null) : InputTypes\FileType
    {
        $inputType = new InputTypes\FileType();
        $inputType->init($this, $dbField, $label);
        $this->_dataTypeList[] = $inputType;

        return $inputType;
    }

    public function image(string $dbField, string $label = null) : InputTypes\ImageType
    {
        $inputType = new InputTypes\ImageType();
        $inputType->init($this, $dbField, $label);
        $this->_dataTypeList[] = $inputType;

        return $inputType;
    }

    public function textarea(string $dbField, string $label = null) : InputTypes\TextareaType
    {
        $inputType = new InputTypes\TextareaType();
        $inputType->init($this, $dbField, $label);
        $this->_dataTypeList[] = $inputType;

        return $inputType;
    }

    public function custom($class, string $dbField, string $label = null)
    {
        $inputType = new $class();

        if (!$inputType instanceof InputTypes\BaseInputType) {
            throw new \Exception($class . ' should extends Biswadeep\FormTool\Http\Libraries\InputTypes\BaseInputType');
        }

        if (!$inputType instanceof InputTypes\ICustomType) {
            throw new \Exception($class . ' should implements Biswadeep\FormTool\Http\Libraries\InputTypes\ICustomType');
        }

        $this->_dataTypeList[] = $inputType;
        $inputType->init($this, $dbField, $label);

        return $inputType;
    }

    public function getInputTypeByDbField(string $dbField)
    {
        foreach ($this->_dataTypeList as $input) {
            if (! $input instanceof DataModel && $input->getDbField() == $dbField)
                return $input;
        }
        
        return null;
    }

    #region later

    /*public function date(string $dbField, string $label = null) : DataType
    {
        $dataType = new DataType('date', $dbField, $label);
        $this->cellList[] = $dataType;

        return $dataType;
    }

    public function time(string $dbField, string $label = null) : DataType
    {
        $dataType = new DataType('time', $dbField, $label);
        $this->cellList[] = $dataType;

        return $dataType;
    }

    public function datetime(string $dbField, string $label = null) : DataType
    {
        $dataType = new DataType('datetime', $dbField, $label);
        $this->cellList[] = $dataType;

        return $dataType;
    }

    public function status(string $dbField, string $label = null) : DataType
    {
        $dataType = new DataType('status', $dbField, $label);
        $this->cellList[] = $dataType;

        return $dataType;
    }

    public function image(string $dbField, string $label = null) : ImageType
    {
        $dataType = new DataType('image', $dbField, $label);
        $this->cellList[] = $dataType;

        return $dataType;
    }*/

    public function select(string $dbField, string $label = null) : InputTypes\SelectType
    {
        $inputType = new InputTypes\SelectType();
        $inputType->init($this, $dbField, $label);
        $this->_dataTypeList[] = $inputType;

        return $inputType;
    }

    /*public function actions($actions) : DataType
    {
        if (!is_array($actions))
            throw new \Exception("Actions columns should be in an array! Like: ['edit', 'delete']");

        $dataType = new DataType('action', '', 'Actions');
        $this->cellList[] = $dataType->width('85px');

        foreach ($actions as $action)
            $this->actions[] = new TableAction($action);

        return $dataType;
    }*/

    #endregion

    #region Multiple

    public function multiple(string $dbField, string $label, Closure $field)
    {
        $dbField = \trim($dbField);

        $subDataModel[$dbField] = new DataModel($dbField, true, $this);
        $subDataModel[$dbField]->label = $label ?? $label ?: \ucfirst($dbField);

        $field($subDataModel[$dbField]);

        $this->_dataTypeList[] = $subDataModel[$dbField];

        return $subDataModel[$dbField];
    }

    public function required($noOfItems = 1)
    {
        $this->multipleRequired = \trim($noOfItems);

        return $this;
    }

    public function sortable($dbField = null)
    {
        $this->isMultipleSortable = true;

        $dbField = \trim($dbField);
        if ($dbField) {
            $this->multipleSortableField = $dbField;
            $this->hidden($dbField)->default(0)->addClass('sort-value');
        }

        return $this;
    }

    public function confirmBeforeDelete()
    {
        $this->isMultipleConfirmBeforeDelete = true;

        return $this;
    }

    public function table($model, $idCol = null, $foreignKeyCol = null, $orderBy = null)
    {
        if ($idCol && $foreignKeyCol) {
            $this->multipleTable = (object)[
                'table'         => \trim($model),
                'id'            => \trim($idCol),
                'foreignKey'    => \trim($foreignKeyCol),
                'orderBy'       => \trim($orderBy)
            ];
        }
        else {
            if (class_exists($model)) {
                throw new \Exception('Class not found. Class: ' . $model);
            }

            $this->multipleModel = $model;

            if ($model && ! isset($model::$foreignKey))
                throw new \Exception('$foreignKey property not defined at ' . $model);
        }

        return $this;
    }

    public function keepId()
    {
        if (! $this->multipleModel && ! $this->multipleTable)
            throw new \Exception('keepId only works with db table, Please assign the table first. And keepId must called at last.');

        if ($this->isMultipleSortable && ! $this->multipleSortableField)
            throw new \Exception('You must pass a dbField in sortable to make work with keepId. And keepId must called at last.');

        if ($this->multipleModel)
            $this->hidden($this->multipleModel::$primaryId);
        elseif ($this->multipleTable)
            $this->hidden($this->multipleTable->id);

        return $this;
    }


    public function getRequired()
    {
        return $this->multipleRequired;
    }

    public function isSortable()
    {
        return $this->isMultipleSortable;
    }

    public function isConfirmBeforeDelete()
    {
        return $this->isMultipleConfirmBeforeDelete;
    }

    public function getModel()
    {
        if ($this->multipleTable)
            return $this->multipleTable;

        return $this->multipleModel;
    }

    public function getSortableField()
    {
        return $this->multipleSortableField;
    }

    #endregion

    public function getKey()
    {
        return $this->key;
    }

    public function getFullKey($key = '')
    {
        if ($this->parentDataModel) {
            $key .= $this->parentDataModel->getKey($key);

            // Preventing array brackets for the first $key
            if (! $this->parentDataModel->isMultiple) {
                $key .= $this->key;
                return $key;
            }
        }

        if ($this->key)
            $key .= '[' . $this->key;
        else
            return '';

        return $key . ']';
    }
}