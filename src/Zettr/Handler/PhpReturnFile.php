<?php

namespace Zettr\Handler;

use Zettr\Message;

class PhpReturnFile extends AbstractHandler {

    const INDENT = '    ';

    protected function _apply() {

        // let's use some speaking variable names... :)
        $file = $this->param1;
        $path = $this->param2;

        if (!is_file($file)) {
            throw new \Exception(sprintf('File "%s" does not exist', $file));
        }
        if (!is_writable($file)) {
            throw new \Exception(sprintf('File "%s" is not writeable', $file));
        }
        if (empty($path)) {
            throw new \Exception('No path defined, use "foo.bar" for ["foo"]["bar"]');
        }
        if (!empty($this->param3)) {
            throw new \Exception('Param3 is not used in this handler and must be empty');
        }

        // read file
        $data = include $file;
        if (!is_array($data)) {
            throw new \Exception(sprintf('Error while reading file "%s", expected array', $file));
        }

        $changes = 0;

        $paths = explode('.', $path);
        $length = count($paths);
        $value = &$data;
        foreach($paths as $i => $key) {
            if (!isset($value[$key])) {
                if ($i === $length - 1) {
                    $value[$key] = null;
                } else {
                    $value[$key] = [];
                }
            }
            $value = &$value[$key];
            if ($i === $length - 1 && $this->value == '--delete--') {
                $changes++;
                unset($value[$key]);
                $this->addMessage(new Message('Path removed'));
            }
        }

        if ($value == $this->value) {
            $this->addMessage(new Message(sprintf('Value "%s" is already in place. Skipping.', $this->value), Message::SKIPPED));
        } else {
            $this->addMessage(new Message(sprintf('Updated value from "%s" to "%s"', $value, $this->value)));
            $value = $this->value;
            $changes++;
        }
        $foo = $data["db"]["connection"]["default"];

        if ($changes > 0) {
            $res = file_put_contents($file, "<?php\nreturn " . $this->varExportShort($data, true) . ";\n");
            if ($res === false) {
                throw new \Exception(sprintf('Error while writing file "%s"', $file));
            }
            $this->setStatus(HandlerInterface::STATUS_DONE);
        } else {
            $this->setStatus(HandlerInterface::STATUS_ALREADYINPLACE);
        }

        return true;
    }

    /**
     * See vendor/magento/framework/App/DeploymentConfig/Writer/PhpFormatter.php
     */
    protected function varExportShort($var, int $depth = 0)
    {
        if (null === $var) {
            return 'null';
        } elseif (!is_array($var)) {
            return var_export($var, true);
        }

        $indexed = array_keys($var) === range(0, count($var) - 1);
        $expanded = [];
        foreach ($var as $key => $value) {
            $expanded[] = str_repeat(self::INDENT, $depth)
                . ($indexed ? '' : $this->varExportShort($key) . ' => ')
                . $this->varExportShort($value, $depth + 1);
        }

        return sprintf("[\n%s\n%s]", implode(",\n", $expanded), str_repeat(self::INDENT, $depth - 1));
    }
}