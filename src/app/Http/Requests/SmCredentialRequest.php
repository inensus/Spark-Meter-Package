<?php


namespace Inensus\SparkMeter\app\Http\Requests;


use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SmCredentialRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     *
     * @return array
     */
    public function rules()
    {
        return [

            'api_url' =>['required',Rule::unique('sm_api_credentials')->ignore($this->id)],
            'authentication_token' => 'required',

        ];
    }
}
