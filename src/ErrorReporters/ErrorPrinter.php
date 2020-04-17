<?php

namespace Imanghafoori\LaravelMicroscope\ErrorReporters;

class ErrorPrinter
{
    public $counts = [
        'view' => [],
        'route'=>[],
        'total' => 0,
        'bladeImport' => [],
        'badRelation' => [],
        'wrongImport' => [],
        'wrongUsedClassError' => [],
        'wrongMethodError' => [],
        'badNamespace' => [],
    ];

    public $printer;

    public $logErrors = true;

    public function view($absPath, $lineContent, $lineNumber, $fileName)
    {
        array_push($this->counts['view'], (new PendingError('view'))
            ->header($this->yellow($fileName.'.blade.php').' does not exist')
            ->errorData(trim($lineContent))
            ->link($absPath, $lineNumber));
    }

    public function route($path, $errorIt, $errorTxt, $absPath = null, $lineNumber = 0)
    {
        array_push($this->counts['route'], (new PendingError('route'))
            ->header($errorIt)
            ->errorData($errorTxt.$this->yellow($path))
            ->link($absPath, $lineNumber));
    }

    public function bladeImport($class, $absPath, $lineNumber)
    {
        $this->pendError($absPath, $lineNumber, $class, 'bladeImport', 'Class does not exist:');
    }

    public function authConf()
    {
        $this->print('The model in the "config/auth.php" is not a valid class');
    }

    public function badRelation($absPath, $lineNumber, $relatedModel)
    {
        $header = 'Wrong model is passed in relation:';

        $this->pendError($absPath, $lineNumber, $relatedModel, 'badRelation', $header);
    }

    public function pendError($path, $lineNumber, $absent, $key, $header)
    {
        array_push($this->counts[$key], (new PendingError($key))
            ->header($header)
            ->errorData($this->yellow($absent).'   <==== does not exist')
            ->link($path, $lineNumber));
    }

    public function wrongImport($absPath, $class, $lineNumber)
    {
        $this->pendError($absPath, $lineNumber, "use $class;", 'wrongImport', 'Wrong import:');
    }

    public function wrongUsedClassError($absPath, $class, $lineNumber)
    {
        $this->pendError($absPath, $lineNumber, $class, 'wrongUsedClassError', 'Class does not exist:');
    }

    public function wrongMethodError($absPath, $class, $lineNumber)
    {
        $this->pendError($absPath, $lineNumber, $class, 'wrongMethodError', 'Method does not exist:');
    }

    public function yellow($msg)
    {
        return "<fg=yellow>$msg</>";
    }

    /**
     * @param  string  $absPath
     * @param  string  $correctNamespace
     * @param  string  $incorrectNamespace
     * @param  int  $lineNumber
     *
     * @return void
     */
    public function badNamespace($absPath, $correctNamespace, $incorrectNamespace, $lineNumber = 4)
    {
        array_push($this->counts['badNamespace'], (new PendingError('badNamespace'))
            ->header('Incorrect namespace: '.$this->yellow("namespace $incorrectNamespace;"))
            ->errorData('namespace fixed to: '.$this->yellow("namespace $correctNamespace;"))
            ->link($absPath, $lineNumber));
    }

    public function print($msg, $path = '  |    ', $len = 81)
    {
        $msgLen = strlen($msg);
        if (strpos($msg, 'yellow')) {
            $msgLen = $msgLen - 14;
        }
        $len = $len - $msgLen;
        if ($len < 0) {
            $len = 0;
        }

        $this->printer->writeln($path.$msg.str_repeat(' ', $len).'|  ');
    }

    public function printHeader($msg)
    {
        $this->print('');
        $number = ++$this->counts['total'];
        $number = '<fg=yellow>'.$number.'  </>';
        $path = "  | $number";

        $this->print($msg, $path);
    }

    public function end()
    {
        $this->print('');
        $this->printer->writeln('  |'.str_repeat('*', 85).'|  ');
    }

    public function printLink($path, $lineNumber = 4)
    {
        if ($path) {
            $filePath = trim(str_replace(base_path(), '', $path), '\\/');
            $this->print('at <fg=green>'.$filePath.'</>'.':<fg=green>'.$lineNumber.'</>', '', 114);
        }
    }

    /**
     * Checks for errors for the run command.
     *
     * @return int
     */
    public function hasErrors()
    {
        $errorsCollection = collect($this->counts);

        return $errorsCollection
            ->flatten()
            ->filter(function ($action) {
                return $action instanceof PendingError;
            })->count();
    }

    /**
     * Logs the errors to the console.
     */
    public function logErrors()
    {
        collect($this->counts)
            ->except('total')
            ->flatten()
            ->each(function ($error) {
                if ($error instanceof PendingError) {
                    $this->printHeader($error->getHeader());
                    $this->print($error->getErrorData());
                    $this->printLink($error->getLinkPath(), $error->getLinkLineNumber());
                    $this->end();
                }
            });
    }
}
