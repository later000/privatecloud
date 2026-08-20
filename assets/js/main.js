(function () {
    'use strict';

    var uploadForm = document.getElementById('upload-form');
    var uploadModal = document.getElementById('upload-modal');

    if (uploadForm && uploadModal) {
        var fileInput = document.getElementById('file-input');
        var uploadFilesInfo = document.getElementById('upload-files');
        var uploadProgressBar = document.getElementById('upload-progress-bar');
        var uploadPct = document.getElementById('upload-pct');
        var uploadResult = document.getElementById('upload-result');
        var uploadClose = document.getElementById('upload-close');
        var uploading = false;

        var closeUploadModal = function () {
            if (!uploading) {
                uploadModal.style.display = 'none';
            }
        };

        uploadClose.addEventListener('click', closeUploadModal);
        uploadModal.addEventListener('click', function (e) {
            if (e.target === uploadModal) {
                closeUploadModal();
            }
        });

        fileInput.addEventListener('change', function () {
            var files = fileInput.files;
            if (!files.length) {
                return;
            }
            uploading = true;

            var names = [];
            for (var i = 0; i < files.length; i++) {
                names.push(files[i].name);
            }
            uploadFilesInfo.textContent = names.length > 1
                ? '正在上传 ' + names.length + ' 个文件：' + names[0] + ' 等'
                : '正在上传：' + names[0];
            uploadProgressBar.style.width = '0%';
            uploadPct.textContent = '0%';
            uploadResult.textContent = '';
            uploadModal.style.display = 'flex';

            var formData = new FormData(uploadForm);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'upload.php');

            xhr.upload.onprogress = function (e) {
                if (e.lengthComputable) {
                    var pct = Math.round(e.loaded / e.total * 100);
                    uploadProgressBar.style.width = pct + '%';
                    uploadPct.textContent = pct + '%';
                }
            };

            xhr.onload = function () {
                var data = null;
                try {
                    data = JSON.parse(xhr.responseText);
                } catch (err) {
                    data = null;
                }
                uploading = false;
                uploadProgressBar.style.width = '100%';

                if (data && data.ok) {
                    var parts = [];
                    if (data.uploaded > 0) {
                        parts.push('成功上传 ' + data.uploaded + ' 个文件');
                    }
                    if (data.errors && data.errors.length) {
                        parts.push(data.errors.join('；'));
                    }
                    uploadPct.textContent = '完成';
                    uploadResult.className = 'upload-result ' + (data.uploaded > 0 ? 'success' : 'error');
                    uploadResult.textContent = parts.join('。') || '没有文件被上传';
                    setTimeout(function () { location.reload(); }, 1500);
                } else if (data) {
                    uploadPct.textContent = '失败';
                    uploadResult.className = 'upload-result error';
                    uploadResult.textContent = data.message || '上传失败，请重试';
                } else {
                    uploadPct.textContent = '失败';
                    uploadResult.className = 'upload-result error';
                    uploadResult.textContent = xhr.status === 413
                        ? '文件过大，服务器已拒绝，请调大 Nginx 与 PHP 上传限制'
                        : '上传失败（HTTP ' + xhr.status + '），请检查服务器配置后重试';
                }
                fileInput.value = '';
            };

            xhr.onerror = function () {
                uploading = false;
                uploadPct.textContent = '失败';
                uploadResult.className = 'upload-result error';
                uploadResult.textContent = '网络错误，上传中断，请重试';
                fileInput.value = '';
            };

            xhr.send(formData);
        });
    }

    var copyButtons = document.querySelectorAll('[data-copy]');
    Array.prototype.forEach.call(copyButtons, function (btn) {
        btn.addEventListener('click', function () {
            var url = btn.getAttribute('data-copy');
            var done = function () {
                var old = btn.textContent;
                btn.textContent = '已复制';
                setTimeout(function () { btn.textContent = old; }, 1500);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(done, function () {
                    fallbackCopy(url);
                    done();
                });
            } else {
                fallbackCopy(url);
                done();
            }
        });
    });

    var previewModal = document.getElementById('preview-modal');
    if (previewModal) {
        var previewTitle = document.getElementById('preview-title');
        var previewBody = document.getElementById('preview-body');
        var previewClose = document.getElementById('preview-close');

        var closePreview = function () {
            previewModal.style.display = 'none';
            previewBody.innerHTML = '';
        };

        previewClose.addEventListener('click', closePreview);
        previewModal.addEventListener('click', function (e) {
            if (e.target === previewModal) {
                closePreview();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && previewModal.style.display !== 'none') {
                closePreview();
            }
        });

        var previewButtons = document.querySelectorAll('[data-preview]');
        Array.prototype.forEach.call(previewButtons, function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-preview');
                var name = btn.getAttribute('data-name');
                var type = btn.getAttribute('data-type');
                var url = 'preview.php?id=' + encodeURIComponent(id);
                var html = '';
                if (type === 'image') {
                    html = '<img src="' + url + '" alt="">';
                } else if (type === 'video') {
                    html = '<video src="' + url + '" controls autoplay></video>';
                } else if (type === 'audio') {
                    html = '<audio src="' + url + '" controls autoplay></audio>';
                } else if (type === 'pdf' || type === 'text') {
                    html = '<iframe src="' + url + '"></iframe>';
                }
                previewTitle.textContent = name;
                previewBody.innerHTML = html;
                previewModal.style.display = 'flex';
            });
        });
    }

    var shareModal = document.getElementById('share-modal');
    if (shareModal) {
        var shareClose = document.getElementById('share-close');
        var shareResult = document.getElementById('share-result');
        var shareResultLabel = document.getElementById('share-result-label');
        var shareResultInput = document.getElementById('share-result-input');
        var shareCopyBtn = document.getElementById('share-copy');
        var shareOpenBtn = document.getElementById('share-open');
        var pendingFileId = 0;

        var closeShare = function () {
            if (pendingFileId === 0) {
                shareModal.style.display = 'none';
            }
        };

        shareClose.addEventListener('click', closeShare);
        shareModal.addEventListener('click', function (e) {
            if (e.target === shareModal) {
                closeShare();
            }
        });

        document.addEventListener('click', function (e) {
            var shareBtn = e.target.closest ? e.target.closest('[data-share-id]') : null;
            if (shareBtn) {
                pendingFileId = parseInt(shareBtn.getAttribute('data-share-id'), 10) || 0;
                if (!pendingFileId) {
                    return;
                }
                shareResult.style.display = 'none';
                shareModal.style.display = 'flex';
                var opts = shareModal.querySelectorAll('.share-type-card');
                for (var i = 0; i < opts.length; i++) {
                    opts[i].disabled = false;
                }
            }
        });

        var shareTypeCards = shareModal.querySelectorAll('.share-type-card');        Array.prototype.forEach.call(shareTypeCards, function (card) {
            card.addEventListener('click', function () {
                var type = card.getAttribute('data-share-type');
                if (!pendingFileId) {
                    return;
                }
                for (var i = 0; i < shareTypeCards.length; i++) {
                    shareTypeCards[i].disabled = true;
                }
                var csrfInput = document.querySelector('input[name="csrf_token"]');
                var fd = new FormData();
                fd.append('file_id', pendingFileId);
                fd.append('type', type);
                if (csrfInput) {
                    fd.append('csrf_token', csrfInput.value);
                }
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'create_share.php');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.onload = function () {
                    var data = null;
                    try {
                        data = JSON.parse(xhr.responseText);
                    } catch (err) {
                        data = null;
                    }
                    for (var i = 0; i < shareTypeCards.length; i++) {
                        shareTypeCards[i].disabled = false;
                    }
                    if (data && data.ok) {
                        shareResultLabel.textContent = type === 'link'
                            ? '直链已生成，点击链接可直接下载：'
                            : '分享页已生成，打开引导页预览并下载：';
                        shareResultInput.value = data.url;
                        shareOpenBtn.href = data.url;
                        shareResult.style.display = 'block';
                        pendingFileId = 0;
                    } else {
                        shareResultLabel.textContent = (data && data.message) || '创建分享失败';
                        shareResultInput.value = '';
                        shareResult.style.display = 'block';
                        pendingFileId = 0;
                    }
                };
                xhr.send(fd);
            });
        });

        shareCopyBtn.addEventListener('click', function () {
            var url = shareResultInput.value;
            if (!url) {
                return;
            }
            var done = function () {
                var old = shareCopyBtn.textContent;
                shareCopyBtn.textContent = '已复制';
                setTimeout(function () { shareCopyBtn.textContent = old; }, 1500);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(done, function () {
                    fallbackCopy(url);
                    done();
                });
            } else {
                fallbackCopy(url);
                done();
            }
        });
    }

    var perSelects = document.querySelectorAll('[data-per]');
    Array.prototype.forEach.call(perSelects, function (sel) {
        sel.addEventListener('change', function () {
            document.cookie = 'pan_per_page=' + sel.value + '; path=/; max-age=31536000';
            location.reload();
        });
    });

    var installForm = document.getElementById('install-form');
    if (installForm) {
        var installPanels = installForm.querySelectorAll('.step-panel');
        var stepItems = document.querySelectorAll('.step-item');
        var showStep = function (n) {
            for (var i = 0; i < installPanels.length; i++) {
                installPanels[i].style.display = installPanels[i].getAttribute('data-panel') === String(n) ? 'block' : 'none';
            }
            for (var i = 0; i < stepItems.length; i++) {
                stepItems[i].classList.toggle('active', stepItems[i].getAttribute('data-step') === String(n));
            }
        };
        installForm.addEventListener('click', function (e) {
            var next = e.target.closest ? e.target.closest('[data-next]') : null;
            var prev = e.target.closest ? e.target.closest('[data-prev]') : null;
            if (next) {
                showStep(parseInt(next.getAttribute('data-next'), 10) + 1);
            } else if (prev) {
                showStep(parseInt(prev.getAttribute('data-prev'), 10) - 1);
            }
        });

        var testDbBtn = document.getElementById('test-db');
        var testResult = document.getElementById('test-result');
        if (testDbBtn && testResult) {
            testDbBtn.addEventListener('click', function () {
                var q = function (name) {
                    var el = installForm.querySelector('[name="' + name + '"]');
                    return el ? el.value : '';
                };
                var fd = new FormData();
                fd.append('action', 'test');
                fd.append('db_host', q('db_host'));
                fd.append('db_port', q('db_port'));
                fd.append('db_name', q('db_name'));
                fd.append('db_user', q('db_user'));
                fd.append('db_pass', q('db_pass'));
                testResult.textContent = '正在测试连接…';
                testResult.className = 'test-result';
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'install.php');
                xhr.onload = function () {
                    var data = null;
                    try {
                        data = JSON.parse(xhr.responseText);
                    } catch (err) {
                        data = null;
                    }
                    if (data && data.ok) {
                        testResult.textContent = data.message || '连接成功';
                        testResult.className = 'test-result ok';
                    } else {
                        testResult.textContent = (data && data.message) || '连接失败';
                        testResult.className = 'test-result bad';
                    }
                };
                xhr.send(fd);
            });
        }
    }

    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
        } catch (e) {}
        document.body.removeChild(ta);
    }
})();
