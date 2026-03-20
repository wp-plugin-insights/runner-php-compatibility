<?php

declare(strict_types=1);

namespace WpPluginInsightsCompatibility\Sniffs\Classes;

use PHPCompatibility\Sniff;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

class NewTypedClassConstantsSniff extends Sniff
{
    public function register(): array
    {
        return [T_CONST];
    }

    public function process(File $phpcsFile, $stackPtr)
    {
        if ($this->supportsBelow('8.2') === false) {
            return;
        }

        if ($this->isClassConstant($phpcsFile, $stackPtr) === false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        $firstToken = $phpcsFile->findNext(Tokens::$emptyTokens, $stackPtr + 1, null, true);

        if ($firstToken === false) {
            return;
        }

        $secondToken = $phpcsFile->findNext(Tokens::$emptyTokens, $firstToken + 1, null, true);

        if ($secondToken === false) {
            return;
        }

        if ($tokens[$firstToken]['code'] !== T_STRING) {
            return;
        }

        if ($tokens[$secondToken]['code'] !== T_STRING) {
            return;
        }

        $phpcsFile->addError(
            'Typed class constants are not supported in PHP 8.2 or earlier',
            $firstToken,
            'Found'
        );
    }
}
