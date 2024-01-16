<?php

use Concrete\Core\Editor\CkeditorEditor;

defined('C5_EXECUTE') or die('Access denied.');

/**
 * @var Concrete\Package\Iubenda\Block\IubendaDoc\Controller $controller
 * @var Concrete\Core\Form\Service\Form $form
 * @var Concrete\Core\Editor\EditorInterface $editor
 * @var array $docTypes
 * @var string $docType
 * @var int|null $docID
 * @var array $renderMethods
 * @var string $renderMethod
 * @var bool $whiteLabel
 * @var string $linkInnerHtml
 * @var array $linkStyles
 * @var string $linkStyle
 * @var string $cssClasses
 * @var bool $noMarkup
 * @var int $docCacheLifetime
 */

?>
<div class="form-group">
    <?= $form->label('docType', t('Document Type')) ?>
    <?= $form->select('docType', $docTypes, $docType, ['required' => 'required']) ?>
</div>

<div class="form-group">
    <?= $form->label('docID', t('Document ID')) ?>
    <?= $form->number('docID', $docID, ['required' => 'required', 'min' => '1']) ?>
</div>

<div class="form-group">
    <?= $form->label('renderMethod', t('Render Method')) ?>
    <?= $form->select('renderMethod', $renderMethods, $renderMethod, ['required' => 'required']) ?>
</div>

<div class="form-group">
    <?= $form->label('linkStyle', t('Link Style')) ?>
    <?= $form->select('linkStyle', $linkStyles, $linkStyle, ['required' => 'required']) ?>
</div>

<div class="form-group">
    <?= $form->label('cssClasses', t('Additional CSS classes')) ?>
    <?= $form->text('cssClasses', $cssClasses) ?>
</div>

<div class="form-group">
    <?= $form->label('linkInnerHtml', t('Text of the link')) ?>
    <?php
    if ($editor instanceof CkeditorEditor && method_exists($editor, 'outputEditorWithOptions')) {
        echo $editor->outputEditorWithOptions('linkInnerHtml', [
            'enterMode' => 2, // CKEDITOR.ENTER_BR
            'shiftEnterMode' => 3, // CKEDITOR.ENTER_DIV
            'height' => 100,
        ], $linkInnerHtml);
    } else {
        echo $editor->outputBlockEditModeEditor('linkInnerHtml', $linkInnerHtml);
    }
    ?>
</div>

<div class="form-group">
    <?= $form->label('docCacheLifetime', t('Cache Lifetime (in seconds)')) ?>
    <?= $form->number('docCacheLifetime', $docCacheLifetime, ['placeholder' => t('Leave empty or set to 0 to avoid using cache')]) ?>
</div>

<div class="form-group">
    <?= $form->label('', t('Options')) ?>
    <div class="checkbox">
        <label>
            <?= $form->checkbox('whiteLabel', '1', $whiteLabel) ?>
            <?= t('White Label') ?>
        </label>
    </div>
    <div class="checkbox">
        <label>
            <?= $form->checkbox('noMarkup', '1', $noMarkup) ?>
            <?= t('Use plain HTML with no styling (only shows the legal version)') ?>
        </label>
    </div>
</div>

<script>
(function() {

function setViewFields() {
    const renderMethod = $('#ccm-block-fields #renderMethod').val();
    const docType = $('#ccm-block-fields #docType').val();
    $('#ccm-block-fields #linkStyle').closest('div.form-group').toggle(renderMethod === <?= json_encode($controller::RENDERMETHOD_POPUP) ?>);
    $('#ccm-block-fields #cssClasses').closest('div.form-group').toggle(renderMethod !== <?= json_encode($controller::RENDERMETHOD_EMBED_STATIC) ?>);
    $('#ccm-block-fields [name="linkInnerHtml"]').closest('div.form-group').toggle([<?= json_encode($controller::RENDERMETHOD_POPUP) ?>, <?= json_encode($controller::RENDERMETHOD_LINK) ?>].includes(renderMethod));
    $('#ccm-block-fields #docCacheLifetime').closest('div.form-group').toggle(renderMethod === <?= json_encode($controller::RENDERMETHOD_EMBED_STATIC) ?>);
    const showWhiteLabel = [<?= json_encode($controller::RENDERMETHOD_POPUP) ?>, <?= json_encode($controller::RENDERMETHOD_EMBED_DYNAMIC) ?> ].includes(renderMethod);
    const showNoMarkup = renderMethod === <?= json_encode($controller::RENDERMETHOD_EMBED_DYNAMIC) ?>
        || (renderMethod === <?= json_encode($controller::RENDERMETHOD_EMBED_STATIC) ?> && docType !== <?= json_encode($controller::DOCTYPE_PRIVACYPOLICY_SIMPLIFIED) ?>);
    $('#ccm-block-fields #whiteLabel').closest('div.form-group').toggle(showWhiteLabel || showNoMarkup);
    $('#ccm-block-fields #whiteLabel').closest('div.checkbox').toggle(showWhiteLabel);
    $('#ccm-block-fields #noMarkup').closest('div.checkbox').toggle(showNoMarkup);
}

try {
    setViewFields();
} catch (_) {
}

$(document).ready(function() {
    setViewFields();
    $('#ccm-block-fields #renderMethod,#ccm-block-fields #docType').on('change', () => setViewFields());
});

})();
</script>