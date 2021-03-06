<?php

namespace Biswadeep\FormTool\Http\Libraries;

use Biswadeep\FormTool\Http\Libraries\InputTypes\BaseInputType;

use Closure;

class CellDefinition
{
    public BaseInputType $inputType;

    public string $fieldType;
    public string $dbField;
    public bool $sortable = true;

    // Label can be nullable
    public $label = '';

    // Styles
    public string $width = '';
    public string $align = '';
    public string $styleCSS = '';
    public $styleClass = [];

    // Private is important
    private function __construct() {}

    public static function Input(BaseInputType $inputType) : CellDefinition
    {
        $cell = new CellDefinition(); 

        $cell->fieldType = '_input';
        $cell->inputType = $inputType;
        
        return $cell;
    }

    public static function Other(string $fieldType, string $dbField, string $label = null) : CellDefinition
    {
        $cell = new CellDefinition(); 
        
        $cell->fieldType = $fieldType;
        $cell->dbField = $dbField;
        $cell->label = $label ?: \ucfirst($dbField);
        
        return $cell;
    }

    public function typeOptions(Closure $options)
    {
        $options($this->inputType);
    }

    public function right() : CellDefinition
    {
        $this->styleClass[] = 'text-right';
        return $this;
    }

    public function left() : CellDefinition
    {
        $this->styleClass[] = 'text-left';
        return $this;
    }

    public function center() : CellDefinition
    {
        $this->styleClass[] = 'text-center';
        return $this;
    }

    public function width($width) : CellDefinition
    {
        $this->width = \trim($width);
        return $this;
    }

    public function label($label) : CellDefinition
    {
        $this->label = \trim($label);
        return $this;
    }

    public function setup() : void
    {
        $this->styleCSS = '';
        if ($this->align)
            $this->styleCSS .= $this->align;

        if ($this->width)
            $this->styleCSS .= 'width:' . $this->width . ';';

        if ($this->styleCSS)
            $this->styleCSS = 'style="' . $this->styleCSS .'"';

        $this->styleClass = implode(' ', $this->styleClass);
        if ($this->styleClass)
            $this->styleClass = 'class="' .  $this->styleClass  . '"';
    }

    // Getter

    public function getLabel()
    {
        return $this->label ? $this->label : $this->inputType->getLabel();
    }

    public function getDbField()
    {
        if ($this->fieldType == '_input')
            return $this->inputType->getDbField();

        return $this->dbField;
    }

    public function setValue($value)
    {
        if ($this->fieldType == '_input')
            return $this->inputType->setValue($value);
    }

    public function getValue()
    {
        if ($this->fieldType == '_input')
            return $this->inputType->getTableValue();
    }
}

class TableAction
{
    public string $action = '';

    public function __construct($action)
    {
        $this->action = $action;
    }
}