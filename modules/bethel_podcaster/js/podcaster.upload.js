jQuery(document).ready(function($) {
    $('.progress').hide();
    $('#file-upload').fileupload({
      url: $(this).attr('action'),
      type: 'POST',
      datatype: 'xml',
      autoUpload: true,
      add: function (e, data) {
          $('.progress').show();
          $('.fileinput-button').hide();
          data.submit();
      },
      fail: function(e, data) {
          $('.progress').hide();
          $('.fileinput-button').show();
          console.log('fail');
          console.log(data);
      },
      progressall: function (e, data) {
          var progress = data.loaded / data.total * 100;
          if (progress >= 5) {
              $('.progress').removeClass('progress-striped active');
              $('.progress .progress-bar').css('width', progress + '%');
              $('.progress .progress-bar').text(Math.round(progress) + '%'); 
          }
          console.log(progress);
      },
      done: function (e, data) {
          $('.progress').hide();
          $('.fileinput-button').show();
          console.log('done');
      }
    });
});