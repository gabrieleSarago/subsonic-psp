<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A form request whose rules cover the parameters of the route.
 *
 * A form request normally validates the body of the request, while the values
 * that reach these endpoints arrive in the URL. They are added to the validated
 * data here, so that a rule can reject a malformed identifier before any call is
 * made to the server.
 *
 * Every parameter validated by this class identifies the resource being asked
 * for, so a value that does not fit means the URL points at nothing. That is
 * answered with a plain "not found" instead of the redirect a browser would get
 * from the default behaviour, which a feed reader and a console have no way of
 * following.
 */
abstract class RoutedRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return array_merge(parent::validationData(), $this->route()?->parameters() ?? []);
    }

    /**
     * @throws NotFoundHttpException always.
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new NotFoundHttpException;
    }
}
