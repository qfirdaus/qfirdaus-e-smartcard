<script>
/*
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 */
function checkFileSize(input) {
  const maxFileSize = 5 * 1024 * 1024;
  if (!input || !input.files || !input.files[0]) {
    return;
  }

  const fileSize = input.files[0].size;
  if (fileSize > maxFileSize) {
    input.setCustomValidity(<?= json_encode(tr('profile_max_file_size', 'Max file size 5MB'), JSON_UNESCAPED_UNICODE) ?>);
    input.reportValidity();
    input.value = '';
    return;
  }

  input.setCustomValidity('');
}
</script>
