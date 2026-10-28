import { createIcons } from './icons.js';
import { toast } from './toast.js';

const MAX_BYTES = 5 * 1024 * 1024;
/** Maximum zoom relative to the minimum cover scale (viewMode 3). */
const MAX_ZOOM_FACTOR = 4;

export function initMemberForm() {
  const root = document.querySelector('[data-member-form]');
  if (!root) return;

  const form = root.querySelector('[data-member-form-el]');
  const fileInput = root.querySelector('[data-photo-input]');
  const cropDataInput = root.querySelector('[data-crop-data]');
  const recropOnlyInput = root.querySelector('[data-recrop-only]');
  const preview = root.querySelector('[data-photo-preview]');
  const clearBtn = root.querySelector('[data-clear-photo]');
  const recropBtn = root.querySelector('[data-recrop-btn]');
  const photoError = root.querySelector('[data-error-for="photo"]');
  const submit = root.querySelector('[data-submit]');
  const submitLabel = root.querySelector('[data-submit-label]');
  const submitLoading = root.querySelector('[data-submit-loading]');

  const modal = document.querySelector('[data-cropper-modal]');
  // Keep the editor outside nested layout contexts (nav/content stacking).
  if (modal && modal.parentElement !== document.body) {
    document.body.appendChild(modal);
  }
  const dialog = modal?.querySelector('.pb-cropper__dialog');
  const stageWrap = modal?.querySelector('.pb-cropper__stage-wrap');
  const stage = modal?.querySelector('[data-cropper-stage]');
  const modalImage = modal?.querySelector('[data-cropper-image]');
  const cancelBtn = modal?.querySelector('[data-cropper-cancel]');
  const confirmBtn = modal?.querySelector('[data-cropper-confirm]');

  let cropper = null;
  let objectUrl = null;
  let pendingFile = null;
  let committedFile = null;
  let mode = 'upload'; // upload | recrop
  let confirmedPreviewUrl = null;
  let openSnapshot = null;
  let minRatio = 1;
  let maxRatio = 4;

  const setPhotoError = (message) => {
    if (!photoError) return;
    if (!message) {
      photoError.hidden = true;
      photoError.textContent = '';
      return;
    }
    photoError.hidden = false;
    photoError.textContent = message;
  };

  const revokeObjectUrl = () => {
    if (objectUrl) {
      URL.revokeObjectURL(objectUrl);
      objectUrl = null;
    }
  };

  const destroyCropper = () => {
    if (cropper) {
      cropper.destroy();
      cropper = null;
    }
  };

  const restoreFileInput = (file) => {
    if (!fileInput) return;
    if (!file) {
      fileInput.value = '';
      return;
    }
    try {
      const transfer = new DataTransfer();
      transfer.items.add(file);
      fileInput.files = transfer.files;
    } catch {
      fileInput.value = '';
    }
  };

  const closeModal = () => {
    destroyCropper();
    if (modal) modal.hidden = true;
    document.body.classList.remove('cropper-open');
    openSnapshot = null;
  };

  const showPreview = (url) => {
    if (!preview) return;
    preview.innerHTML = `<img src="${url}" alt="">`;
  };

  /**
   * Size the editing stage as a true square that fits between header and actions.
   * A square container + viewMode 3 lets portrait images pan fully top/bottom.
   */
  const fitSquareStage = () => {
    if (!dialog || !stage || !stageWrap) return 0;

    const styles = getComputedStyle(dialog);
    const padX = (parseFloat(styles.paddingLeft) || 0) + (parseFloat(styles.paddingRight) || 0);
    const availableWidth = Math.max(0, dialog.clientWidth - padX);
    const availableHeight = Math.max(0, stageWrap.clientHeight);

    const size = Math.floor(Math.max(140, Math.min(availableWidth, availableHeight)));
    stage.style.width = `${size}px`;
    stage.style.height = `${size}px`;
    stage.style.aspectRatio = 'auto';
    return size;
  };

  const fillCropBox = () => {
    if (!cropper) return;
    const container = cropper.getContainerData();
    if (!container.width || !container.height) return;
    cropper.setCropBoxData({
      left: 0,
      top: 0,
      width: container.width,
      height: container.height,
    });
  };

  const refreshZoomLimits = () => {
    if (!cropper) return;
    const imageData = cropper.getImageData();
    if (!imageData.naturalWidth || !imageData.naturalHeight) return;

    // With viewMode 3 the cover scale fills the (now square) container.
    const container = cropper.getContainerData();
    const cover = Math.max(
      container.width / imageData.naturalWidth,
      container.height / imageData.naturalHeight
    );
    minRatio = cover;
    maxRatio = cover * MAX_ZOOM_FACTOR;
  };

  const resizeCropper = () => {
    if (!cropper) return;
    fitSquareStage();
    cropper.resize();
    fillCropBox();
    refreshZoomLimits();
    const imageData = cropper.getImageData();
    const ratio = imageData.width / imageData.naturalWidth;
    if (ratio < minRatio) {
      cropper.zoomTo(minRatio);
    } else if (ratio > maxRatio) {
      cropper.zoomTo(maxRatio);
    }
    fillCropBox();
  };

  const openCropper = (src) => {
    if (!modal || !modalImage || typeof window.Cropper === 'undefined') {
      toast('The cropper could not load. Please refresh and try again.', 'danger');
      return;
    }

    openSnapshot = {
      cropData: cropDataInput?.value || '',
      recropOnly: recropOnlyInput?.value || '0',
      committedFile,
    };

    destroyCropper();
    modalImage.removeAttribute('src');
    modalImage.src = src;
    modal.hidden = false;
    document.body.classList.add('cropper-open');

    // Wait for the opaque shell to paint at full size before measuring.
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        if (modal.hidden) return;

        fitSquareStage();

        cropper = new window.Cropper(modalImage, {
          aspectRatio: 1,
          // Fill the square container so the crop selection matches the stage.
          viewMode: 3,
          dragMode: 'move',
          autoCropArea: 1,
          responsive: true,
          restore: false,
          checkOrientation: true,
          background: false,
          modal: false,
          guides: false,
          center: false,
          highlight: false,
          cropBoxMovable: false,
          cropBoxResizable: false,
          toggleDragModeOnDblclick: false,
          zoomable: true,
          zoomOnTouch: true,
          zoomOnWheel: true,
          wheelZoomRatio: 0.1,
          movable: true,
          scalable: false,
          rotatable: false,
          zoom(event) {
            const next = event.detail.ratio;
            // Reject zoom that would uncover the crop area or exceed a sensible max.
            if (next < minRatio - 1e-6 || next > maxRatio + 1e-6) {
              event.preventDefault();
            }
          },
          ready() {
            fillCropBox();
            refreshZoomLimits();
            // Snap to the exact cover scale so wheel/pinch share one baseline.
            cropper.zoomTo(minRatio);
            resizeCropper();
            window.setTimeout(resizeCropper, 50);
          },
        });
      });
    });
  };

  fileInput?.addEventListener('change', () => {
    setPhotoError('');
    const file = fileInput.files?.[0];
    if (!file) return;

    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      setPhotoError('Please choose a JPEG, PNG, or WebP photograph.');
      restoreFileInput(committedFile);
      return;
    }
    if (file.size > MAX_BYTES) {
      setPhotoError('That photograph is too large. Please use a file under 5 MB.');
      restoreFileInput(committedFile);
      return;
    }

    mode = 'upload';
    pendingFile = file;
    if (recropOnlyInput) recropOnlyInput.value = '0';
    revokeObjectUrl();
    objectUrl = URL.createObjectURL(file);
    openCropper(objectUrl);
  });

  recropBtn?.addEventListener('click', () => {
    const url = recropBtn.getAttribute('data-original-url');
    if (!url) return;
    setPhotoError('');
    mode = 'recrop';
    pendingFile = null;
    if (fileInput) fileInput.value = '';
    if (recropOnlyInput) recropOnlyInput.value = '1';
    openCropper(url);
  });

  cancelBtn?.addEventListener('click', () => {
    // Restore pre-open photo/crop state. Do not keep a half-finished selection.
    if (cropDataInput) {
      cropDataInput.value = openSnapshot?.cropData || '';
    }
    if (recropOnlyInput) {
      recropOnlyInput.value = openSnapshot?.recropOnly || '0';
    }

    pendingFile = null;
    restoreFileInput(openSnapshot?.committedFile || committedFile || null);
    revokeObjectUrl();
    closeModal();
  });

  confirmBtn?.addEventListener('click', () => {
    if (!cropper || !cropDataInput) return;

    // Enforce cover constraints one last time before reading coordinates.
    refreshZoomLimits();
    const imageData = cropper.getImageData();
    const ratio = imageData.width / imageData.naturalWidth;
    if (ratio < minRatio - 1e-6) {
      cropper.zoomTo(minRatio);
    } else if (ratio > maxRatio + 1e-6) {
      cropper.zoomTo(maxRatio);
    }

    const data = cropper.getData(true);
    if (
      !Number.isFinite(data.x)
      || !Number.isFinite(data.y)
      || !Number.isFinite(data.width)
      || !Number.isFinite(data.height)
      || data.width < 1
      || data.height < 1
    ) {
      toast('That crop looks invalid. Please try again.', 'danger');
      return;
    }

    cropDataInput.value = JSON.stringify({
      x: data.x,
      y: data.y,
      width: data.width,
      height: data.height,
      rotate: data.rotate || 0,
    });

    if (mode === 'upload' && pendingFile) {
      committedFile = pendingFile;
      restoreFileInput(committedFile);
      if (recropOnlyInput) recropOnlyInput.value = '0';
    } else if (mode === 'recrop') {
      committedFile = null;
      if (fileInput) fileInput.value = '';
      if (recropOnlyInput) recropOnlyInput.value = '1';
    }

    const canvas = cropper.getCroppedCanvas({
      width: 512,
      height: 512,
      imageSmoothingEnabled: true,
      imageSmoothingQuality: 'high',
      fillColor: '#342720',
    });

    if (!canvas) {
      toast('Could not create the crop preview.', 'danger');
      return;
    }

    if (confirmedPreviewUrl) {
      URL.revokeObjectURL(confirmedPreviewUrl);
    }
    canvas.toBlob((blob) => {
      if (!blob) {
        toast('Could not create the crop preview.', 'danger');
        return;
      }
      confirmedPreviewUrl = URL.createObjectURL(blob);
      showPreview(confirmedPreviewUrl);
      if (clearBtn) clearBtn.hidden = false;
      revokeObjectUrl();
      closeModal();
      createIcons(preview);
    }, 'image/jpeg', 0.92);
  });

  clearBtn?.addEventListener('click', () => {
    if (cropDataInput) cropDataInput.value = '';
    if (recropOnlyInput) recropOnlyInput.value = '0';
    if (fileInput) fileInput.value = '';
    pendingFile = null;
    committedFile = null;
    if (confirmedPreviewUrl) {
      URL.revokeObjectURL(confirmedPreviewUrl);
      confirmedPreviewUrl = null;
    }
    const existing = preview?.querySelector('[data-existing-photo]');
    if (existing) {
      preview.innerHTML = '';
      preview.appendChild(existing.cloneNode(true));
    } else if (preview) {
      preview.innerHTML = `<div class="member-card__fallback" aria-hidden="true" data-photo-placeholder><i data-lucide="camera"></i></div>`;
      createIcons(preview);
    }
    clearBtn.hidden = true;
    setPhotoError('');
  });

  form?.addEventListener('submit', (event) => {
    const needsCrop = (fileInput?.files?.length > 0) || recropOnlyInput?.value === '1';
    if (needsCrop && !cropDataInput?.value) {
      event.preventDefault();
      setPhotoError('Please crop the photograph before saving.');
      return;
    }

    if (submit) {
      submit.disabled = true;
      submit.classList.add('is-loading');
    }
    if (submitLabel) submitLabel.hidden = true;
    if (submitLoading) submitLoading.hidden = false;
  });

  modal?.addEventListener('click', (event) => {
    // Desktop backdrop only — the dialog fills the screen on mobile.
    if (event.target === modal) {
      cancelBtn?.click();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && modal && !modal.hidden) {
      cancelBtn?.click();
    }
  });

  window.addEventListener('resize', () => {
    if (!modal?.hidden) {
      resizeCropper();
    }
  });
}

document.addEventListener('DOMContentLoaded', () => {
  initMemberForm();
});
