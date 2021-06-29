<?php

namespace mmaurice\unipay\core\interfaces;

interface PluginInterface
{
    const MODE_TEST = 'test';
    const MODE_PROD = 'product';

    const CHOICE_NO = 'no';
    const CHOICE_YES = 'yes';
    const CHOICE_ON = 'on';
    const CHOICE_OFF = 'off';

    const ALIAS_PROCESSING = 'processing';
    const ALIAS_SUCCESS = 'success';
    const ALIAS_FAIL = 'fail';

    public function __construct($properties = []);
    public function run();
    public function makeProcessing();
    public function makeSuccess();
    public function makeFail();
    public function makeLink();
    public function makeForm();
}
