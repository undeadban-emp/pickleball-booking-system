export default function galleryUpload({ uploadUrl }) {
    return {
        queue: [],
        uploading: false,

        get doneCount() {
            return this.queue.filter((f) => f.status === 'done').length;
        },

        onFilesSelected(fileList) {
            this.queue = Array.from(fileList).map((file) => ({ file, name: file.name, status: 'pending' }));
            this.uploadNext();
        },

        // Uploads exactly one file per request, waiting for each to finish
        // before starting the next - avoids overwhelming the server with a
        // large multi-file batch and lets progress show per image.
        async uploadNext() {
            const next = this.queue.find((f) => f.status === 'pending');

            if (!next) {
                this.uploading = false;
                if (this.queue.length > 0 && this.queue.every((f) => f.status === 'done')) {
                    window.location.reload();
                }
                return;
            }

            this.uploading = true;
            next.status = 'uploading';

            const formData = new FormData();
            formData.append('image', next.file);

            try {
                const res = await fetch(uploadUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        Accept: 'application/json',
                    },
                    body: formData,
                });

                next.status = res.ok ? 'done' : 'error';
            } catch (e) {
                next.status = 'error';
            }

            this.uploadNext();
        },
    };
}
