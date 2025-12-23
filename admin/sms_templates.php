<?php
require('../db.php');
require('header.php');

 checkPermission(4);

// Handle add/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sms_id = isset($_POST['sms_id']) ? intval($_POST['sms_id']) : 0;
    $sms_name = mysqli_real_escape_string($con, $_POST['sms_name']);
    $tamil_sms = mysqli_real_escape_string($con, $_POST['tamil_sms']);
    $english_sms = mysqli_real_escape_string($con, $_POST['english_sms']);
    $sinhala_sms = mysqli_real_escape_string($con, $_POST['sinhala_sms']);
    $status = isset($_POST['status']) ? 1 : 0;

    if ($sms_id > 0) {
        $sql = "UPDATE sms_templates SET sms_name='$sms_name', tamil_sms='$tamil_sms', english_sms='$english_sms', sinhala_sms='$sinhala_sms', status=$status WHERE sms_id=$sms_id";
    } else {
        $sql = "INSERT INTO sms_templates (sms_name, tamil_sms, english_sms, sinhala_sms, status) VALUES ('$sms_name', '$tamil_sms', '$english_sms', '$sinhala_sms', $status)";
    }
    mysqli_query($con, $sql);
   
}

// Fetch all templates
$result = mysqli_query($con, "SELECT * FROM sms_templates");
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="main-header d-flex justify-content-between align-items-center">
            <h2>Manage SMS Templates</h2>
            <button class="btn btn-success" data-toggle="modal" data-target="#smsModal" onclick="clearSmsModal()">+ Add New SMS</button>
        </div>
        <div class="card">
            <div class="card-block">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>SMS Name</th>
                            <th>English</th>
                            <th>Tamil</th>
                            <th>Sinhala</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                        <tr>
                            <td><?= htmlspecialchars($row['sms_name']) ?></td>
                            <td><?= htmlspecialchars($row['english_sms']) ?></td>
                            <td><?= htmlspecialchars($row['tamil_sms']) ?></td>
                            <td><?= htmlspecialchars($row['sinhala_sms']) ?></td>
                            <td><?= $row['status'] ? 'Active' : 'Inactive' ?></td>
                            <td>
                                <button class="btn btn-primary btn-sm" 
                                    onclick="editSms(<?= $row['sms_id'] ?>, '<?= htmlspecialchars(addslashes($row['sms_name'])) ?>', '<?= htmlspecialchars(addslashes($row['english_sms'])) ?>', '<?= htmlspecialchars(addslashes($row['tamil_sms'])) ?>', '<?= htmlspecialchars(addslashes($row['sinhala_sms'])) ?>', <?= $row['status'] ?>)">
                                    Edit
                                </button>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- SMS Modal -->
<div class="modal fade" id="smsModal" tabindex="-1" role="dialog" aria-labelledby="smsModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form method="post" id="smsForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="smsModalLabel">SMS Template</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="sms_id" id="sms_id">
          <div class="form-group">
            <label>SMS Name</label>
            <input type="text" class="form-control" name="sms_name" id="sms_name" required>
          </div>
          <div class="form-group">
            <label>English SMS</label>
            <div class="sms-edit-box" id="english_sms_edit" contenteditable="true" style="border:1px solid #ccc; min-height:60px; padding:6px; border-radius:4px;"></div>
            <textarea name="english_sms" id="english_sms" style="display:none;"></textarea>
            <small id="english_sms_count"></small>
          </div>
          <ul id="tag-suggestion" class="list-group"></ul>

          
          <div class="form-group">
            <label>Tamil SMS</label>
            <div class="sms-edit-box" id="tamil_sms_edit" contenteditable="true" style="border:1px solid #ccc; min-height:60px; padding:6px; border-radius:4px;"></div>
            <textarea name="tamil_sms" id="tamil_sms" style="display:none;"></textarea>
            <small id="tamil_sms_count"></small>
          </div>
          <div class="form-group">
            <label>Sinhala SMS</label>
            <div class="sms-edit-box" id="sinhala_sms_edit" contenteditable="true" style="border:1px solid #ccc; min-height:60px; padding:6px; border-radius:4px;"></div>
            <textarea name="sinhala_sms" id="sinhala_sms" style="display:none;"></textarea>
            <small id="sinhala_sms_count"></small>
          </div>
          <div class="form-group">
            <label>Status</label>
            <select class="form-control" name="status" id="status">
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Save</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<ul id="tag-suggestion" class="list-group" style="position:absolute; z-index:9999; display:none; min-width:150px;"></ul>

<style>
#tag-suggestion {
   
    z-index: 9999;
    display: none;
    min-width: 150px;
    background: #fff;
    border: 1px solid #ccc;
    border-radius: 4px;
    max-height: 180px;
    overflow-y: auto;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
#tag-suggestion li {
    padding: 6px 12px;
    cursor: pointer;
}
#tag-suggestion li.active, #tag-suggestion li:hover {
    background: #007bff;
    color: #fff;
}
.sms-tag {
    background: #ffe066;
    color: #d35400;
    border-radius: 3px;
    padding: 0 2px;
    font-weight: bold;
}
</style>

<script>
function countChars(id) {
    var val = document.getElementById(id).value;
    var len = val.length;
    var pages = Math.ceil(len / 160);
    document.getElementById(id + '_count').innerText = pages + ' page(s) ' + len + '/160';
}

function editSms(id, name, english, tamil, sinhala, status) {
    document.getElementById('sms_id').value = id;
    document.getElementById('sms_name').value = name;
    // Set the contenteditable div for English
    document.getElementById('english_sms_edit').innerText = english;
    // If you use Tamil/Sinhala, do the same for them
    document.getElementById('tamil_sms_edit').innerText = tamil;
    document.getElementById('sinhala_sms_edit').innerText = sinhala;
    document.getElementById('status').value = status;
    // Trigger input to sync textarea and highlighting
    $('#english_sms_edit').trigger('input');
    $('#tamil_sms_edit').trigger('input');
    $('#sinhala_sms_edit').trigger('input');
    $('#smsModal').modal('show');
}

function clearSmsModal() {
    document.getElementById('sms_id').value = '';
    document.getElementById('sms_name').value = '';
    document.getElementById('english_sms').value = '';
    document.getElementById('tamil_sms').value = '';
    document.getElementById('sinhala_sms').value = '';
    document.getElementById('status').value = 1;
    countChars('english_sms');
    countChars('tamil_sms');
    countChars('sinhala_sms');
}

const tags = ['@due_date','@payable_amount','@paid_amount','@Receipt_no'];
let currentBox = null;

// Utility: get caret offset in plain text
function getCaretCharacterOffsetWithin(element) {
    let caretOffset = 0, sel = window.getSelection();
    if (sel.rangeCount > 0) {
        let range = sel.getRangeAt(0);
        let preCaretRange = range.cloneRange();
        preCaretRange.selectNodeContents(element);
        preCaretRange.setEnd(range.endContainer, range.endOffset);
        caretOffset = preCaretRange.toString().length;
    }
    return caretOffset;
}

// Utility: set caret at plain text offset, skipping over tags
function setCaretPosition(element, offset) {
    let nodeStack = [element], node, charIndex = 0, found = false, range = document.createRange();
    range.setStart(element, 0);
    range.collapse(true);
    while ((node = nodeStack.pop()) && !found) {
        if (node.nodeType === 3) {
            let nextCharIndex = charIndex + node.length;
            if (offset >= charIndex && offset <= nextCharIndex) {
                range.setStart(node, offset - charIndex);
                range.collapse(true);
                found = true;
            }
            charIndex = nextCharIndex;
        } else if (node.nodeType === 1 && node.className === "sms-tag") {
            // Count tag as one character for caret offset
            charIndex += node.innerText.length;
        } else {
            let i = node.childNodes.length;
            while (i--) nodeStack.push(node.childNodes[i]);
        }
    }
    let sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
}

// Highlight tags in text
function highlightTags(text) {
    let safe = text.replace(/</g, "&lt;").replace(/>/g, "&gt;");
    tags.forEach(tag => {
        const re = new RegExp(tag.replace('@', '\\@'), 'g');
        safe = safe.replace(re, `<span class="sms-tag">${tag}</span>`);
    });
    return safe;
}

// Sync contenteditable to textarea, highlight, and restore caret
function syncTextarea(editId, textareaId, countId) {
    const editBox = document.getElementById(editId);
    const textarea = document.getElementById(textareaId);
    const countBox = document.getElementById(countId);
    // Save caret position in plain text
    let caretPos = getCaretCharacterOffsetWithin(editBox);
    let plain = editBox.innerText;
    textarea.value = plain;
    let len = plain.length;
    let pages = Math.ceil(len / 160);
    countBox.innerText = pages + ' page(s) ' + len + '/160';
    // Highlight tags
    editBox.innerHTML = highlightTags(plain);
    // Restore caret position
    setCaretPosition(editBox, caretPos);
}

// Insert tag at caret, replacing the partial tag being typed
function insertTagAtCaret(editableDiv, tag) {
    let sel = window.getSelection();
    if (!sel.rangeCount) return;
    let range = sel.getRangeAt(0);
    let node = sel.anchorNode;
    let text = node.textContent;
    let caretPos = sel.anchorOffset;
    let before = text.substring(0, caretPos).replace(/@[\w_]*$/, '');
    let after = text.substring(caretPos);
    node.textContent = before + tag + after;
    // Highlight tags and restore caret
    let newCaretPos = (before + tag).length;
    editableDiv.innerHTML = highlightTags(editableDiv.innerText);
    setCaretPosition(editableDiv, newCaretPos);
    $(editableDiv).trigger('input');
}

// Tag autocomplete for all boxes
$('.sms-edit-box').on('keyup click', function(e) {
    currentBox = this;
    let sel = window.getSelection();
    if (!sel.rangeCount) return $('#tag-suggestion').hide();
    let range = sel.getRangeAt(0);
    let node = sel.anchorNode;
    if (!node) return $('#tag-suggestion').hide();
    let text = node.textContent || '';
    let caretPos = sel.anchorOffset;
    let beforeCaret = text.substring(0, caretPos);
    let match = beforeCaret.match(/@[\w_]*$/);
    let $dropdown = $('#tag-suggestion');
    if (match) {
        let term = match[0];
        let filtered = tags.filter(t => t.startsWith(term));
        if (filtered.length) {
            $dropdown.empty();
            filtered.forEach((tag, i) => {
                $dropdown.append('<li'+(i===0?' class="active"':'')+'>'+tag+'</li>');
            });
            // Position dropdown below the box
            let offset = $(this).offset();
            $dropdown.css({
                top: offset.top + $(this).outerHeight(),
                left: offset.left,
                display: 'block'
            });
        } else {
            $dropdown.hide();
        }
    } else {
        $dropdown.hide();
    }
});

// Mouse click on suggestion
$(document).on('mousedown', '#tag-suggestion li', function(e) {
    e.preventDefault();
    insertTagAtCaret(currentBox, $(this).text());
    $('#tag-suggestion').hide();
    $(currentBox).trigger('input');
});

// Keyboard navigation
$(document).on('keydown', function(e) {
    let $dropdown = $('#tag-suggestion');
    if ($dropdown.is(':visible')) {
        let $items = $dropdown.find('li');
        let $active = $items.filter('.active');
        let idx = $items.index($active);
        if (e.key === 'ArrowDown') {
            $active.removeClass('active');
            $items.eq((idx+1)%$items.length).addClass('active');
            e.preventDefault();
        } else if (e.key === 'ArrowUp') {
            $active.removeClass('active');
            $items.eq((idx-1+$items.length)%$items.length).addClass('active');
            e.preventDefault();
        } else if (e.key === 'Enter') {
            if ($active.length) {
                insertTagAtCaret(currentBox, $active.text());
                $dropdown.hide();
                $(currentBox).trigger('input');
                e.preventDefault();
            }
        } else if (e.key === 'Escape') {
            $dropdown.hide();
        }
    }
});

// Hide dropdown on click outside
$(document).on('mousedown', function(e) {
    if (!$(e.target).closest('.sms-edit-box, #tag-suggestion').length) {
        $('#tag-suggestion').hide();
    }
});

// Sync on input for all boxes
$('#english_sms_edit').on('input', function() {
    syncTextarea('english_sms_edit', 'english_sms', 'english_sms_count');
});
$('#tamil_sms_edit').on('input', function() {
    syncTextarea('tamil_sms_edit', 'tamil_sms', 'tamil_sms_count');
});
$('#sinhala_sms_edit').on('input', function() {
    syncTextarea('sinhala_sms_edit', 'sinhala_sms', 'sinhala_sms_count');
});
</script>

<?php include 'footer.php'; ?>