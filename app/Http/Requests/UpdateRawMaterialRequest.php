<?php

namespace App\Http\Requests;

class UpdateRawMaterialRequest extends RawMaterialRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->entity();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'raw_material_key' => 'required',
        ];
    }
}
