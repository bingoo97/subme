  
<div id="messanger_upload" class="messenger-upload-modal" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="messenger-upload-backdrop" data-messenger-upload-close onclick="return closeMessengerUpload();"></div>
  <div class="messenger-upload-dialog">
    <div class="messenger-upload-card">
      <div class="messenger-upload-header">
        <h2>{$t.chat_upload|default:'Upload image'}</h2>
        <button type="button" class="messenger-upload-close" data-messenger-upload-close onclick="return closeMessengerUpload();" aria-label="{$t.close|default:'Close'}">
          <i class="fa fa-times" aria-hidden="true"></i>
        </button>
      </div>
      <div class="messenger-upload-body">
        <form method="POST" action="" enctype="multipart/form-data" onsubmit="return false;">
          <input type="hidden" name="_csrf" id="messenger_upload_csrf" value="{$csrf_token|default:''}">
          <div class="form-group">
            <p>{$t.chat_upload_help|default:'Only image files are allowed: .jpg, .png, .gif. Larger images are automatically optimized before saving.'}</p>
            <div class="upload-btn-wrapper">
              <input type="file" class="filestyle" name="file" id="file" onchange="return previewFile();" accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif"><br>
              <img src="" style="display:none;" class="img-responsive" id="preview_img" alt="{$t.chat_preview|default:'Preview'}">
            </div>
            <div class="messenger-upload-progress" id="messenger_upload_progress" style="display:none;">
              <div class="messenger-upload-progress__label">
                <span>{$t.chat_uploading|default:'Uploading image'}</span>
                <strong id="messenger_upload_progress_value">0%</strong>
              </div>
              <div class="messenger-upload-progress__bar">
                <div class="messenger-upload-progress__fill" id="messenger_upload_progress_fill" style="width:0%;"></div>
              </div>
            </div>
          </div>
          <hr />
          <div class="messenger-upload-actions">
            <button type="button" class="btn btn-success" id="button_upload2" onclick="return upload2();" disabled="disabled">
              <i class="fa fa-file-image-o" aria-hidden="true"></i> {$t.chat_upload_file|default:'Upload file'}
            </button>
            <button type="button" class="btn btn-default" data-messenger-upload-close onclick="return closeMessengerUpload();">
              {$t.close|default:'Close'}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
