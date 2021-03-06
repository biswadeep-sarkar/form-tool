<?php

namespace Biswadeep\FormTool\Http\Libraries;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Biswadeep\FormTool\Http\Libraries\InputTypes\InputType;
use Illuminate\Support\Facades\DB;

abstract class FormStatus
{
    public const Create = 1;
    public const Store = 2;
    public const Edit = 3;
    public const Update = 4;
    public const Destroy = 4;
}

class Form
{
    private $_dataModel;
    private $_resource;
    private $_model;

    private int $formStatus = 0;

    private $_request;

    private $_editId;

    private $_url = '';

    private $resultData = null;
    private $postData = null;
    private $oldData = null;

    function __construct($resource, $model, DataModel $dataModel = null)
    {
        $this->_resource = $resource;
        $this->_model = $model;

        if ($dataModel)
            $this->_dataModel = $dataModel;
        else
            $this->_dataModel = DataModel::getInstance();

        $this->_dataModel->form = $this;

        $this->_url = config('form-tool.adminURL') . '/' . $this->_resource->route;
    }

    public function init()
    {
        $this->_request = request();
        $method = $this->_request->method();

        if ("POST" == $method) {
            $this->formStatus = FormStatus::Store;
            return $this->store();
        }
        else if ('PUT' == $method) {
            $this->formStatus = FormStatus::Update;
            return $this->update();
        }
        else if ('DELETE' == $method) {
            $this->formStatus = FormStatus::Destroy;
            return $this->destroy();
        }
        else if (strpos($this->_request->getRequestUri(), '/edit')) {
            $this->formStatus = FormStatus::Edit;
            return $this->edit();
        }
    }

    public function getForm()
    {
        $data['inputs'] = '';
        foreach ($this->_dataModel->getList() as $input) {
            if ($input instanceof DataModel) {
                $data['inputs'] .= '<div class="form-group"><label>'. $input->label .'</label>';
                $data['inputs'] .= $this->getMultipleFields($input);
                $data['inputs'] .= '</div>';
            }
            else {
                $data['inputs'] .= $input->getHTML();
            }
        }

        $isEdit = $this->formStatus == FormStatus::Edit;
            
        $data['isEdit'] = $isEdit;
        if ($isEdit) {
            $data['action'] = config('form-tool.adminURL') . '/' . $this->_resource->route . '/' . $this->_editId;
        }
        else {
            $data['action'] = config('form-tool.adminURL') . '/' . $this->_resource->route;
        }

        return view('form-tool::crud.components.form', $data);
    }

    private function getMultipleFields($model)
    {
        $key = $model->getFullKey();
        $keyName = str_replace(['[', ']'], '-', $key);

        $classes = '';
        if ($model->isSortable())
            $classes .= ' table-sortable';

        if ($model->isConfirmBeforeDelete())
            $classes .= ' confirm-delete';

        $data = '<table class="table table-bordered'. $classes .'" id="'. $keyName .'" data-required="'. $model->getRequired() .'"><thead>
        <tr class="active">';

        $totalCols = 0;
        foreach ($model->getList() as $field) {
            if (! $field instanceof DataModel) {
                if ($field->getType() != InputType::Hidden) {
                    $data .= '<th>' . $field->getLabel() . '</th>';
                    $totalCols++;
                }
            }
            else {
                $data .= '<th></th>';
                $totalCols++;
            }
        }

        $data .= '<th></th></tr></thead><tbody>';
        
        $template = $this->getTemplate($model, $key, $keyName);

        // Let's get data for multiple fields if its Edit
        $result = null;
        if ($this->formStatus == FormStatus::Edit) {
            $dbModel = $model->getModel();
            if ($dbModel) {
                if ($dbModel instanceof \stdClass) {
                    $where = [$dbModel->foreignKey => $this->_editId];
                    
                    $query = DB::table($dbModel->table)->where($where);                    
                    if ($dbModel->orderBy)
                        $query->orderBy($dbModel->orderBy, 'asc');

                    $result = $query->get();
                }
                else {
                    if ($model->getSortableField())
                        $dbModel::$orderBy = $model->getSortableField();
                    
                    $where = [$dbModel::$foreignKey => $this->_editId];
                    $result = $dbModel::getWhere($where);
                }
            }
            else if (isset($this->resultData->{$key})) {
                $result = \json_decode($this->resultData->{$key});
            }
            
            if ($result) {
                $i = 0;
                foreach ($result as $row) {
                    foreach ($model->getList() as $field) {
                        if (isset($row->{$field->getDbField()})) {
                            $field->setValue($row->{$field->getDbField()});
                        }
                    }

                    $data .= $this->getTemplate($model, $key, $keyName, $i++);
                }
            }
        }

        if ($model->getRequired() > 0) {
            // Check if the required items is greater than the items already saved
            $appendCount = 0;
            if ($result)
                $appendCount = $model->getRequired() - count($result);
            else if (!$result || $this->formStatus == FormStatus::Create)
                $appendCount = $model->getRequired();
            
            for ($i = 0; $i < $appendCount; $i++) {
                $data .= $template;
            }
        }

        $data .= '</tbody>
            <tfoot>
                <tr>
                    <td colspan="'. ++$totalCols .'" class="text-right">
                        <a class="btn btn-primary btn-xs d_add"><i class="fa fa-plus"></i></a>
                    </td>
                </tr>
            </tfoot>
        </table>';

        $data .= '<script>template["'. $keyName .'"]=`'. $template .'`</script>';

        return $data;
    }

    private function getTemplate($model, $key, $keyName, $index = 0)
    {
        $template = '<tr class="d_block">';

        $hidden = '';
        foreach ($model->getList() as $field) {
            if ($field instanceof DataModel)
                $template .= '<td>' . $this->getMultipleFields($field) . '</td>';
            else {
                if ($field->getType() == InputType::Hidden)
                    $hidden .= $field->getHTMLMultiple($key, $index);
                else
                    $template .= '<td>' . $field->getHTMLMultiple($key, $index) . '</td>';
            }
        }

        $template .= '<td colspan="2" class="text-right">';

        if ($model->isSortable()) {
            $template .= $hidden
                . '<a class="btn btn-default handle btn-xs" style="display:none"><i class="fa fa-arrows"></i></a>&nbsp; ';
        }

        $template .= '<a class="btn btn-default btn-xs text-danger d_remove" style="display:none"><i class="fa fa-times"></i></a>';
        $template .= '</td></tr>';

        return $template;
    }

    public function edit($id = false)
    {
        if (!$id) {
            $url = $this->_request->getRequestUri();

            $matches = [];
            $t = preg_match('/'. $this->_resource->route .'\/([^\/]*)\/edit/', $url, $matches);
            if (count($matches) > 1)
                $id = $matches[1];
            else
                return redirect($this->_url)/*->action([get_class($this->_resource), 'index'])*/->with('error', 'Could not fetch "id"! Call edit manually.');
        }

        $this->_editId = $id;

        $this->resultData = $this->_model::getOne($id);

        foreach ($this->_dataModel->getList() as $input) {
            if (! $input instanceof DataModel && isset($this->resultData->{$input->getDbField()})) {
                $input->setValue($this->resultData->{$input->getDbField()});
            } 
        }
    }

    private function store()
    {
        $validate = $this->validate();
        if ($validate !== true)
            return $validate;
        
        $this->createPostData();
        
        $insertId = $this->_model::add($this->postData);

        if ($insertId) {
            $this->_editId = $insertId;

            $this->afterSave();
        }

        return redirect($this->_url)->with('success', 'Data added successfully!');
    }

    private function update($id = null)
    {
        if ($id) {
            $this->_editId = $id;
        }
        else if (! $this->_editId) {
            $parse = $this->parseEditId();
            if (true !== $parse)
                return $parse;
        }

        $validate = $this->validate();
        if ($validate !== true)
            return $validate;

        if (! $this->oldData)
            $this->oldData = $this->_model::getOne($this->_editId);

        // TODO: 
        // validations
        //      permission to update
        //      can update this row

        $this->createPostData();

        $affected = $this->_model::updateOne($this->_editId, $this->postData);

        if ($affected > 0) {
            $this->afterSave();
        }

        return redirect($this->_url)->with('success', 'Data updated successfully!');
    }

    private function afterSave()
    {
        if (! $this->_editId)
            return;

        $result = $this->_model::getOne($this->_editId);
        foreach ($this->_dataModel->getList() as $input) {
            if ($input instanceof DataModel)
                continue;
            
            if ($this->formStatus == FormStatus::Store)
                $response = $input->afterStore($result);
            else
                $response = $input->afterUpdate($this->oldData, $result);
        }

        $this->saveMultipleFields();
    }

    private function saveMultipleFields()
    {
        foreach ($this->_dataModel->getList() as $input) {
            if (! $input instanceof DataModel || ! $input->getModel())
                continue;
            
            $model = $input->getModel();

            $foreignKey = null;
            if ($model instanceof \stdClass) {
                $foreignKey = $model->foreignKey;
            }
            else {
                if (! isset($model::$foreignKey))
                    throw new \Exception('$foreignKey property not defined at ' . $model);

                $foreignKey = $model::$foreignKey;
            }

            $data = [];
            if (isset($this->_request->{$input->getKey()}) && is_array($this->_request->{$input->getKey()})) {
                foreach ($this->_request->{$input->getKey()} as $row) {
                    $dataRow = [];
                    foreach ($input->getList() as $field) {
                        $dataRow[$field->getDbField()] = $row[$field->getDbField()];
                    }

                    $dataRow[$foreignKey] = $this->_editId;
                    
                    $data[] = $dataRow;
                }
            }

            $where = [$foreignKey => $this->_editId];
            if ($model instanceof \stdClass) {
                DB::table($model->table)->where($where)->delete();
                if (count($data))
                    DB::table($model->table)->insert($data);
            }
            else {
                $model::deleteWhere($where);
                if (count($data))
                    $model::addMany($data);
            }
        }
    }

    private function validate()
    {
        $validationType = $this->formStatus == FormStatus::Store ? 'store' : 'update';

        $fields = $labels = [];
        foreach ($this->_dataModel->getList() as $input) {
            if ($input instanceof DataModel)
                continue;

            $fields[$input->getDbField()] = $input->getValidations($validationType);

            $labels[$input->getDbField()] = $input->getLabel();
        }

        $validator = \Validator::make($this->_request->all(), $fields, [], $labels);
 
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
 
        $this->postData = $validator->validated();

        return true;
    }

    private function createPostData($id = null)
    {
        if ($this->formStatus == FormStatus::Store) {
            $this->postData['createdBy'] = Session::has('user') ? Session::get('user')->userId : 0;
            $this->postData['createdAt'] = date('Y-m-d H:i:s');
        } else {
            if ($id) {
                $this->_editId = $id;
            }
            else if (! $this->_editId) {
                $parse = $this->parseEditId();
                if (true !== $parse)
                    return $parse;
            }

            if (! $this->oldData)
                $this->oldData = $this->_model::getOne($this->_editId);

            $this->postData['updatedBy'] = Session::has('user') ? Session::get('user')->userId : 0;
            $this->postData['updatedAt'] = date('Y-m-d H:i:s');
        }

        $this->formatMultiple();

        foreach ($this->_dataModel->getList() as $input) {
            if ($input instanceof DataModel) {
                if (! $input->getModel())
                    $this->postData[$input->getKey()] = \json_encode($this->_request{$input->getKey()});
                
                continue;
            }

            $response = null;
            if ($this->formStatus == FormStatus::Store)
                $response = $input->beforeStore((object)$this->postData);
            else
                $response = $input->beforeUpdate($this->oldData, (object)$this->postData);

            if ($response !== null) {
                $this->postData[$input->getDbField()] = $response;
            }

            if (! $this->postData[$input->getDbField()] && $input->getDefaultValue() !== null)
                $this->postData[$input->getDbField()] = $input->getDefaultValue();
        }
    }

    private function parseEditId()
    {
        $url = $this->_request->getRequestUri();

        $matches = [];
        $t = preg_match('/'. $this->_resource->route .'\/([^\/]*)\/?/', $url, $matches);
        if (count($matches) > 1) {
            $this->_editId = $matches[1];
            return true;
        }
        
        return redirect($this->_url)->with('error', 'Could not fetch "id"! Call update manually.');
    }

    private function formatMultiple()
    {
        $data = $this->_request->all();

        $merge = [];
        foreach ($data as $name => $value) {
            if (is_array($value)) {
                $keys = array_keys($value);
                if (! $keys)
                    continue;

                $totalRows = count($value[$keys[0]]);
                $totalKeys = count($keys);

                $newData = [];
                for ($i = 0; $i < $totalRows; $i++) {
                    $newRow = [];
                    for ($j = 0; $j < $totalKeys; $j++) {
                        $newRow[$keys[$j]] = $value[$keys[$j]][$i];
                    }

                    $newData[] = $newRow;
                }

                $merge[$name] = $newData;
            }
        }

        $this->_request->merge($merge);

        /* Need this for file upload and other callbacks
        
        $arrayToMerge = [];
        foreach ($this->_dataModel->getList() as $input) {
            if ($input instanceof DataModel) {
                $row = [];

                foreach ($input->getList() as $field) {
                    //$response = $input->beforeStore((object)$this->postData);
                    //$row[$field->dbField()] = 
                }

                $arrayToMerge[$input->getKey()] = 1;                
            }
        }*/
    }

    private function destroy($id = false)
    {
        if (!$id) {
            $url = $this->_request->getRequestUri();

            $matches = [];
            $t = preg_match('/'. $this->_resource->route .'\/([^\/]*)\/?/', $url, $matches);
            if (count($matches) > 1)
                $id = $matches[1];
            else
                return redirect($this->_url)->with('error', 'Could not fetch "id"! Call update manually.');
        }

        // TODO: 
        // validations
        //      permission to delete
        //      can delete this row

        $result = $this->_model::getOne($id);
        foreach ($this->_dataModel->getList() as $field) {
            if ($field instanceof DataModel) {
                // TODO:
            }
            else
                $field->beforeDestroy($result);
        }

        $affected = $this->_model::deleteOne($id);

        if ($affected > 0) {
            foreach ($this->_dataModel->getList() as $field) {
                if ($field instanceof DataModel) {
                    // TODO:
                }
                else
                    $field->afterDestroy($result);
            }
        }

        return redirect($this->_url)->with('success', 'Data deleted successfully!');
    }

    public function getPostData($id = null)
    {
        $this->createPostData($id);

        return $this->postData;
    }

    public function setPostData($data)
    {
        $this->postData = $data;
    }

    public function getModel()
    {
        return $this->_model;
    }

    public function getEditId()
    {
        return $this->_editId;
    }
}