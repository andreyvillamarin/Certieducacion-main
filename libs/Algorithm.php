<?php
namespace RobThree\Auth;

enum Algorithm: string
{
    case Sha1 = 'sha1';
    case Sha256 = 'sha256';
    case Sha512 = 'sha512';
}