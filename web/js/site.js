/* EntityIndex public site: AJAX forms (waitlist, opt-out). Requires jQuery; yii.js adds the CSRF header. */
jQuery(function ($) {
  $('form[data-ajax]').on('submit', function (e) {
    e.preventDefault();
    var $f = $(this), $btn = $f.find('button[type=submit]'), $err = $f.find('[data-error]'), label = $btn.text();
    $err.addClass('d-none');
    $btn.prop('disabled', true).text($btn.data('busy') || 'Sending…');
    $.ajax({ url: $f.attr('action'), method: 'POST', data: $f.serialize(), dataType: 'json' })
      .done(function () {
        $f.replaceWith($('<div class="alert alert-success mb-0" role="status"></div>').text($f.data('success') || 'Thanks!'));
      })
      .fail(function (xhr) {
        var msg = (xhr.responseJSON && xhr.responseJSON.error) || 'Something went wrong. Please try again.';
        $err.text(msg).removeClass('d-none');
        $btn.prop('disabled', false).text(label);
      });
  });
});
