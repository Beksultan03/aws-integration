<?php

namespace App\Http\API\Controllers;

use App\Http\API\Requests\TrustedEmails\IndexRequest;
use App\Http\API\Requests\TrustedEmails\StoreRequest;
use App\Http\API\Requests\TrustedEmails\UpdateRequest;
use App\Models\TrustedEmail;
use App\Services\EmailPasswords\EmailPasswordService;

class EmailPasswordController extends BaseController
{

    private EmailPasswordService $emailPasswordService;

    public function __construct(EmailPasswordService $emailPasswordService)
    {
        $this->emailPasswordService = $emailPasswordService;
    }

    /**
     * @OA\Get(
     *     path="/email-passwords",
     *     summary="Retrieve password chunks for a trusted email",
     *     description="Returns the password split into chunks with random characters added if the IP address is trusted and the email exists.",
     *     tags={"Trusted Emails"},
     *     @OA\Parameter(
     *         name="email",
     *         in="query",
     *         description="The email for which to retrieve the password",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response with password chunks",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(type="string", example="abcd1234efgh")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error response",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="something went wrong")
     *         )
     *     )
     * )
     */

    public function index(IndexRequest $request)
    {

        $email = $request->input('email');
        $result = $this->emailPasswordService->generatePassword($email);

        return $this->responseOk($result);
    }

    /**
     * @OA\Post(
     *     path="/email-passwords",
     *     summary="Create a new trusted email",
     *     description="Stores a new trusted email with an encrypted password.",
     *     tags={"Trusted Emails"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="ip_address", type="string", example="192.168.1.1"),
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", example="johndoe@example.com"),
     *             @OA\Property(property="password", type="string", example="secret123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Trusted email created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Trusted email created successfully!"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="ip_address", type="string", example="192.168.1.1"),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="johndoe@example.com"),
     *                 @OA\Property(property="password", type="string", example="encrypted_password_here")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Validation error.")
     *         )
     *     )
     * )
     */

    public function store(StoreRequest $request)
    {
        $trustedEmail = TrustedEmail::query()->create([
            'email' => $request->input('email'),
            'password' => encrypt($request->input('password')),
        ]);

        return response()->json([
            'message' => 'Trusted email created successfully!',
            'data' => $trustedEmail,
        ], 200);
    }

    public function update(UpdateRequest $request)
    {
        $trustedEmail = TrustedEmail::query()->where('email', $request->input('email'))->first();

        if (!$trustedEmail) {
            return $this->error('Email not found');
        }

        $trustedEmail->update([
            'password' => encrypt($request->input('password')),
        ]);

        return response()->json([
            'message' => 'Trusted email updated successfully!',
            'data' => $trustedEmail,
        ], 200);
    }
}
