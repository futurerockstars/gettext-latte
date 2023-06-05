<?php

namespace h4kuna\Gettext;

use Exception;

class GettextException extends Exception
{

}

class FileNotFoundException extends GettextException
{

}

class DirectoryNotFoundException extends GettextException
{

}

class DomainDoesNotExistsException extends GettextException
{

}

class UnsupportedOperationSystemException extends GettextException
{

}

class UnsupportedTranslateMacroException extends GettextException
{

}
