    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toasts = document.querySelectorAll('.toast');

            toasts.forEach(function (toast) {
                const closeBtn = toast.querySelector('.toast-close');

                const hideToast = function () {
                    toast.classList.add('toast-hidden');
                    setTimeout(function () {
                        toast.remove();
                    }, 250);
                };

                if (closeBtn) {
                    closeBtn.addEventListener('click', hideToast);
                }

                setTimeout(hideToast, 4000);
            });
        });
    </script>
</body>
</html>
