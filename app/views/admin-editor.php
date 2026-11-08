<?php /** @var string $editorName @var string $editorValue @var string $csrf */ ?>
<div class="editor" data-csrf="<?= $e($csrf) ?>">
  <div class="editor-label">Text <span class="muted small">(Write in Visual like WordPress, or edit the HTML in Code. Both stay in sync.)</span></div>
  <div class="ed-tabs" role="tablist"><button type="button" data-mode="visual" class="on">Visual</button><button type="button" data-mode="code">Code (HTML)</button><button type="button" data-mode="split">Split</button></div>
  <div class="toolbar" role="toolbar">
    <button type="button" data-cmd="h2" title="Big heading">H2</button>
    <button type="button" data-cmd="h3" title="Small heading">H3</button>
    <button type="button" data-cmd="p" title="Paragraph">P</button>
    <button type="button" data-cmd="b" title="Bold"><b>B</b></button>
    <button type="button" data-cmd="i" title="Italic"><i>I</i></button>
    <button type="button" data-cmd="link" title="Link">Link</button>
    <button type="button" data-cmd="ul" title="Bullet list">&bull; List</button>
    <button type="button" data-cmd="ol" title="Numbered list">1. List</button>
    <button type="button" data-cmd="quote" title="Quote">Quote</button>
    <button type="button" data-cmd="button" title="Button link">Button</button>
    <button type="button" data-cmd="image" title="Upload an image">Image</button>
    <button type="button" data-cmd="paras" title="Turn plain lines into paragraphs">Auto &para;</button>
    <input type="file" accept="image/*" class="editor-file" hidden>
  </div>
  <div class="ed-panes">
    <div class="editor-visual prose" contenteditable="true" spellcheck="true"></div>
    <textarea name="<?= $e($editorName) ?>" rows="18" class="editor-text" spellcheck="true" hidden><?= $e($editorValue) ?></textarea>
  </div>
</div>
