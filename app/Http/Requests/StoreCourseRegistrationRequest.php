<?php

namespace App\Http\Requests;

use App\AccountType;
use App\Models\CourseRegistrationItem;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->account_type === AccountType::Student
            && $user->studentProfile !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['save', 'submit'])],
            'items' => ['required', 'array', 'min:1', 'max:24'],
            'items.*.course_offering_id' => ['required', 'integer', 'distinct', 'exists:course_offerings,id'],
            'items.*.source' => [
                'required',
                Rule::in([
                    CourseRegistrationItem::SourceCurrentSet,
                    CourseRegistrationItem::SourceCarryover,
                    CourseRegistrationItem::SourceOutstandingRequirement,
                    CourseRegistrationItem::SourceElective,
                ]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Select at least one course before saving this registration.',
            'items.min' => 'Select at least one course before saving this registration.',
            'items.*.course_offering_id.distinct' => 'A course appears more than once. Remove the duplicate course and try again.',
        ];
    }
}
