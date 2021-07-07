<?php
/**
 * 2019-2021 Team Ever
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 *  @author    Team Ever <https://www.team-ever.com/>
 *  @copyright 2019-2021 Team Ever
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Everpspdfimport extends Module
{
    private $html;
    private $postErrors = array();
    private $postSuccess = array();

    public function __construct()
    {
        $this->name = 'everpspdfimport';
        $this->tab = 'administration';
        $this->version = '1.2.3';
        $this->author = 'Team Ever';
        $this->need_instance = 0;
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('Ever PS PDF Import');
        $this->description = $this->l('Import PDF files to products based on EAN13 or product reference');
        $this->isSeven = Tools::version_compare(_PS_VERSION_, '1.7', '>=') ? true : false;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        Configuration::updateValue('EVERPSPDFIMPORT_EAN13', false);
        Configuration::updateValue('EVERPSPDFIMPORT_PREFIX', '');
        Configuration::updateValue('EVERPSPDFIMPORT_PRODUCT_PREFIX', '');
        Configuration::updateValue('EVERPSPDFIMPORT_FOLDER', '');

        return parent::install();
    }

    public function uninstall()
    {
        Configuration::deleteByName('EVERPSPDFIMPORT_EAN13');
        Configuration::deleteByName('EVERPSPDFIMPORT_PREFIX');
        Configuration::deleteByName('EVERPSPDFIMPORT_PRODUCT_PREFIX');
        Configuration::deleteByName('EVERPSPDFIMPORT_FOLDER');
        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        if (((bool)Tools::isSubmit('submitEverpspdfimportModule')) == true) {
            $this->postValidation();

            if (!count($this->postErrors)) {
                $this->postProcess();
            }
        }
        if (((bool)Tools::isSubmit('submitTruncateAttachments')) == true) {
            $this->postValidation();

            if (!count($this->postErrors)) {
                $this->truncateAttachments();
            }
        }
        if (((bool)Tools::isSubmit('submitImportPdf')) == true) {
            $this->postValidation();

            if (!count($this->postErrors)) {
                $files_dir = glob(
                    _PS_ROOT_DIR_
                    .'/'
                    .Configuration::get('EVERPSPDFIMPORT_FOLDER')
                    .'/*'
                );
                foreach ($files_dir as $file) {
                    if (is_file($file)
                        // && pathinfo($file, PATHINFO_EXTENSION) == 'pdf'
                        && !strpos(basename($file), 'index')
                    ) {
                        $fileExist = false;
                        $fileExist = Db::getInstance()->getValue('SELECT file
                            FROM `'._DB_PREFIX_.'attachment`
                            WHERE file_name = "'.basename($file).'"');
                        if ($fileExist) {
                            continue;
                        }
                        $this->addAttachmentImport(
                            $file,
                            basename($file),
                            basename($file)
                        );
                    }
                }
            }
        }

        // Display errors
        if (count($this->postErrors)) {
            foreach ($this->postErrors as $error) {
                $this->html .= $this->displayError($error);
            }
        }

        // Display confirmations
        if (count($this->postSuccess)) {
            foreach ($this->postSuccess as $success) {
                $this->html .= $this->displayConfirmation($success);
            }
        }

        $this->context->smarty->assign(array(
            'everpspdfimport_dir' => $this->_path
        ));

        $this->html .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/header.tpl');
        $this->html .= $this->renderForm();
        $this->html .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/footer.tpl');

        return $this->html;
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitEverpspdfimportModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'buttons' => array(
                    'importPdf' => array(
                        'name' => 'submitImportPdf',
                        'type' => 'submit',
                        'class' => 'btn btn-default pull-right',
                        'icon' => 'process-icon-refresh',
                        'title' => $this->l('Import PDF attachments')
                    ),
                    'truncateAttachments' => array(
                        'name' => 'submitTruncateAttachments',
                        'type' => 'submit',
                        'class' => 'btn btn-default pull-right',
                        'icon' => 'process-icon-refresh',
                        'title' => $this->l('Truncate all attachments')
                    ),
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Files are ean13 named'),
                        'desc' => $this->l('Set yes for ean13.pdf named files'),
                        'hint' => $this->l('Else product reference will be used'),
                        'name' => 'EVERPSPDFIMPORT_EAN13',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'label' => $this->l('Enter PDF folder name'),
                        'desc' => $this->l('Place all your PDF files on this folder'),
                        'hint' => $this->l('If folder doesn\'t exist, import won\'t work'),
                        'name' => 'EVERPSPDFIMPORT_FOLDER',
                        'required' => true,
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'label' => $this->l('PDF filenames for Prestashop'),
                        'desc' => $this->l('Will be the name of all imported files'),
                        'hint' => $this->l('Leave empty for no use'),
                        'name' => 'EVERPSPDFIMPORT_NAMES',
                        'lang' => true,
                        'required' => false,
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => $this->l('PDF descriptions for Prestashop'),
                        'desc' => $this->l('Will be the name of all imported files'),
                        'hint' => 'Leave empty for no use',
                        'autoload_rte' => true,
                        'lang' => true,
                        'name' => 'EVERPSPDFIMPORT_DESC',
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'label' => $this->l('Enter PDF file prefix'),
                        'desc' => $this->l('You can set a specific prefix to pdf names'),
                        'hint' => $this->l('Leave empty for not use'),
                        'name' => 'EVERPSPDFIMPORT_PREFIX',
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'label' => $this->l('Enter product reference prefix'),
                        'desc' => $this->l('You can set a specific prefix to product references'),
                        'hint' => $this->l('Leave empty for not use'),
                        'name' => 'EVERPSPDFIMPORT_PRODUCT_PREFIX',
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        $attachment_names = array();
        $attachment_descriptions = array();
        foreach (Language::getLanguages(false) as $lang) {
            $attachment_names[$lang['id_lang']] = (Tools::getValue(
                'EVERPSPDFIMPORT_NAMES_'.$lang['id_lang']
            )) ? Tools::getValue(
                'EVERPSPDFIMPORT_NAMES_'.$lang['id_lang']
            ) : '';
            $attachment_descriptions[$lang['id_lang']] = (Tools::getValue(
                'EVERPSPDFIMPORT_DESC_'.$lang['id_lang']
            )) ? Tools::getValue(
                'EVERPSPDFIMPORT_DESC_'.$lang['id_lang']
            ) : '';
        }
        return array(
            'EVERPSPDFIMPORT_NAMES' => (!empty(
                $attachment_names[(int)Configuration::get('PS_LANG_DEFAULT')]
            )) ? $attachment_names : Configuration::getInt(
                'EVERPSPDFIMPORT_NAMES'
            ),
            'EVERPSPDFIMPORT_DESC' => (!empty(
                $attachment_descriptions[(int)Configuration::get('PS_LANG_DEFAULT')]
            )) ? $attachment_descriptions : Configuration::getInt(
                'EVERPSPDFIMPORT_DESC'
            ),
            'EVERPSPDFIMPORT_EAN13' => Configuration::get(
                'EVERPSPDFIMPORT_EAN13'
            ),
            'EVERPSPDFIMPORT_PREFIX' => Configuration::get(
                'EVERPSPDFIMPORT_PREFIX'
            ),
            'EVERPSPDFIMPORT_PRODUCT_PREFIX' => Configuration::get(
                'EVERPSPDFIMPORT_PRODUCT_PREFIX'
            ),
            'EVERPSPDFIMPORT_FOLDER' => Configuration::get(
                'EVERPSPDFIMPORT_FOLDER'
            ),
        );
    }

    private function postValidation()
    {
        if (Tools::isSubmit('submitEverpspdfimportModule')) {
            if (Tools::getValue('EVERPSPDFIMPORT_EAN13')
                && !Validate::isBool(Tools::getValue('EVERPSPDFIMPORT_EAN13'))
            ) {
                $this->postErrors[] = $this->l('Error: ean13 or reference is not valid');
            }

            if (!Tools::getValue('EVERPSPDFIMPORT_FOLDER')
                || !Validate::isDirName(Tools::getValue('EVERPSPDFIMPORT_FOLDER'))
            ) {
                $this->postErrors[] = $this->l('Error: folder name is not valid');
            }

            if (Tools::getValue('EVERPSPDFIMPORT_FOLDER')
                && Validate::isDirName(Tools::getValue('EVERPSPDFIMPORT_FOLDER'))
            ) {
                $pdf_folder = Tools::getValue('EVERPSPDFIMPORT_FOLDER');
                $valid = false;
                foreach(glob(_PS_ROOT_DIR_.'/*', GLOB_ONLYDIR) as $dir) {
                    $dirname = basename($dir);
                    if ($dirname == $pdf_folder) {
                        $valid = true;
                    }
                }
                if (!$valid) {
                    $this->postErrors[] = $this->l('Error: folder does not exists');
                }
            }

            if (Tools::getValue('EVERPSPDFIMPORT_PREFIX')
                && !Validate::isString(Tools::getValue('EVERPSPDFIMPORT_PREFIX'))
            ) {
                $this->postErrors[] = $this->l('Error: prefix is not valid');
            }

            if (Tools::getValue('EVERPSPDFIMPORT_PRODUCT_PREFIX')
                && !Validate::isString(Tools::getValue('EVERPSPDFIMPORT_PRODUCT_PREFIX'))
            ) {
                $this->postErrors[] = $this->l('Error: product prefix is not valid');
            }
            // Multilingual validation
            foreach (Language::getLanguages(false) as $lang) {
                if (Tools::getValue('EVERPSPDFIMPORT_NAMES_'.$lang['id_lang'])
                    && !Validate::isGenericName(Tools::getValue('EVERPSPDFIMPORT_NAMES_'.$lang['id_lang']))
                ) {
                    $this->postErrors[] = $this->l(
                        'Error: Prestashop PDF names not valid for lang '
                    ).$lang['iso_code'];
                }
                if (Tools::getValue('EVERPSPDFIMPORT_DESC_'.$lang['id_lang'])
                    && !Validate::isCleanHtml(Tools::getValue('EVERPSPDFIMPORT_DESC_'.$lang['id_lang']))
                ) {
                    $this->postErrors[] = $this->l(
                        'Error: Prestashop PDF description not valid for lang '
                    ).$lang['iso_code'];
                }
            }
        }
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $attachment_names = array();
        $attachment_descriptions = array();
        foreach (Language::getLanguages(false) as $lang) {
            $attachment_names[$lang['id_lang']] = (
                Tools::getValue('EVERPSPDFIMPORT_NAMES_'
                    .$lang['id_lang'])
            ) ? Tools::getValue(
                'EVERPSPDFIMPORT_NAMES_'
                .$lang['id_lang']
            ) : '';
            $attachment_descriptions[$lang['id_lang']] = (
                Tools::getValue('EVERPSPDFIMPORT_DESC_'
                    .$lang['id_lang'])
            ) ? Tools::getValue(
                'EVERPSPDFIMPORT_DESC_'
                .$lang['id_lang']
            ) : '';
        }
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            if ($key == 'EVERPSPDFIMPORT_NAMES') {
                Configuration::updateValue(
                    'EVERPSPDFIMPORT_NAMES',
                    $attachment_names,
                    true
                );
            } elseif ($key == 'EVERPSPDFIMPORT_DESC') {
                Configuration::updateValue(
                    'EVERPSPDFIMPORT_DESC',
                    $attachment_descriptions,
                    true
                );
            } else {
                Configuration::updateValue($key, Tools::getValue($key));
            }
        }
    }

    /**
     * Import and attach  files to product
     * @param string filename, string name, string description
     */
    public function addAttachmentImport($filename, $name, $description)
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if (Configuration::get('EVERPSPDFIMPORT_EAN13')) {
            $ref = 'ean13';
        } else {
            $ref = 'reference';
        }

        // Save product reference with prefix or not
        if (Configuration::get('EVERPSPDFIMPORT_PRODUCT_PREFIX')) {
            $based_data = Configuration::get('EVERPSPDFIMPORT_PRODUCT_PREFIX')
            .pathinfo($filename, PATHINFO_FILENAME);
        } else {
            $based_data = pathinfo($filename, PATHINFO_FILENAME);
        }

        // Save base_data using prefix or not
        if (Configuration::get('EVERPSPDFIMPORT_PREFIX')) {
            $based_data = str_replace(
                Configuration::get('EVERPSPDFIMPORT_PREFIX'),
                '',
                $based_data
            );
        }

        // Remove trailing slash
        $pdf_folder = rtrim(
            Configuration::get('EVERPSPDFIMPORT_FOLDER'),
            '/'
        );

        foreach(glob(_PS_ROOT_DIR_.'/*', GLOB_ONLYDIR) as $dir) {
            $dirname = basename($dir);
            if ($dirname == $pdf_folder) {
                $valid = true;
            }
        }

        if (!$valid) {
            die('folder not found. Please add folder '.$pdf_folder.' to root folder');
        }

        // Create attachment
        $file_we = str_replace(
            Configuration::get('EVERPSPDFIMPORT_PREFIX'),
            '',
            basename($filename)
        );
        $file_name = str_replace(
            '.'.$ext,
            '',
            $file_we
        );
        $attachment = new Attachment();
        $languages = Language::getLanguages();
        $attachment_names = Configuration::getInt('EVERPSPDFIMPORT_NAMES');
        $attachment_descriptions = Configuration::getInt('EVERPSPDFIMPORT_DESC');
        foreach ($languages as $language) {
            $attachment_name = Tools::substr($attachment_names[$language['id_lang']], 0, 32);
            $attachment_description = $attachment_descriptions[$language['id_lang']];
            if (isset($attachment_name)
                && !empty($attachment_name)
                && Validate::isGenericName($attachment_name)
            ) {
                $attachment->name[$language['id_lang']] = $attachment_name;
            } else {
                $attachment->name[$language['id_lang']] = $file_name;
            }
            if (isset($attachment_description)
                && !empty($attachment_description)
            ) {
                $attachment->description[$language['id_lang']] = $attachment_description;
            } else {
                $attachment->description[$language['id_lang']] = $description;
            }
        }

        $attachment->file_size = filesize($filename);
        $attachment->file = sha1($filename);
        $attachment->file_name = $file_we;

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $attachment->mime = finfo_file($finfo, $filename);

        $attachment->add();

        $sqlfile = 'SELECT file
            FROM `'._DB_PREFIX_.'attachment`
            WHERE file_name = "'.pSQL($file_we).'"';

        $everfile = Db::getInstance()->getValue($sqlfile);

        $newpath = _PS_DOWNLOAD_DIR_.$everfile;
        if (rename($filename, $newpath)) {
            $sql = array();
            $success = true;
            $sql[] =
                'INSERT INTO '._DB_PREFIX_.'product_attachment
                SELECT
                pp.id_product,
                pal.id_attachment
                FROM '._DB_PREFIX_.'attachment pal, '._DB_PREFIX_.'product pp
                WHERE pp.'.pSQL($ref).' = "'.pSQL($based_data).'"
                AND pal.file_name = "'.pSQL($file_we).'"';
            $sql[] =
                'UPDATE '._DB_PREFIX_.'product
                SET cache_has_attachments = 1
                WHERE '.pSQL($ref).' = "'.pSQL($based_data).'"';

            foreach ($sql as $s) {
                if (!Db::getInstance()->execute($s)) {
                    $success = false;
                }
            }
            $attachment->update();
            return $success;
        }
    }

    private function truncateAttachments()
    {
        $sql = array();
        $sql[] = 'TRUNCATE '._DB_PREFIX_.'attachment';
        $sql[] = 'TRUNCATE '._DB_PREFIX_.'product_attachment';
        $success = true;
        foreach ($sql as $s) {
            if (!Db::getInstance()->execute($s)) {
                $success = false;
            }
        }
        return $success;
    }
}
