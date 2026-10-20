<?php

namespace App\Http\Controllers;

use App\Support\BranchScope;
use App\Support\NapBoxes;
use Symfony\Component\HttpFoundation\Response;

/** The photo of a NAP box for the admin pages: a branch admin sees their own branch's boxes, the super admin every branch's. */
class NapBoxPhotoController extends Controller
{
    public function show(int $box): Response
    {
        return NapBoxes::photoResponse($box, BranchScope::current());
    }
}
