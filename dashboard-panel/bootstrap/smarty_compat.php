<?php

// Backward compatibility aliases for legacy code using global Smarty class names.
if (class_exists(\Smarty\Smarty::class) && !class_exists('Smarty', false)) {
    class_alias(\Smarty\Smarty::class, 'Smarty');
}

if (class_exists(\Smarty\Exception::class) && !class_exists('SmartyException', false)) {
    class_alias(\Smarty\Exception::class, 'SmartyException');
}

