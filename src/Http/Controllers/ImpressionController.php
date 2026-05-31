<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Http\Controllers;

use Cjmellor\Engageify\Support\ImpressionRecorder;
use Cjmellor\Engageify\Support\ImpressionToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ImpressionController
{
    public function __invoke(Request $request): Response
    {
        $verified = ImpressionToken::verify(token: (string) $request->input('token'));

        abort_if($verified === null, code: Response::HTTP_FORBIDDEN);

        ImpressionRecorder::record(morphType: $verified['type'], morphId: $verified['id']);

        return response()->noContent();
    }
}
