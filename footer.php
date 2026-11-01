    </main>

    <!-- FOOTER -->
    <footer class="bg-slate-900 text-slate-300 mt-auto border-t border-slate-800">
        <div class="max-w-7xl mx-auto px-4 py-12">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">
                
                <div>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-9 h-9 bg-blue-600 rounded-xl flex items-center justify-center text-white">
                            <i class="fa-solid fa-mobile-screen-button"></i>
                        </div>
                        <span class="text-lg font-black text-white">موبایل <span class="text-blue-500">استور</span></span>
                    </div>
                    <p class="text-xs leading-relaxed text-slate-400 mb-4">
                        فروشگاه اینترنتی <?= htmlspecialchars(SITE_NAME) ?>، مرجع تخصصی آنلاین فروش جدیدترین گوشی‌های هوشمند همراه با گارانتی ۱۸ ماهه معتبر.
                    </p>
                    <div class="flex items-center gap-3">
                        <a href="#" class="w-9 h-9 rounded-xl bg-slate-800 hover:bg-blue-600 flex items-center justify-center"><i class="fa-brands fa-instagram"></i></a>
                        <a href="#" class="w-9 h-9 rounded-xl bg-slate-800 hover:bg-blue-600 flex items-center justify-center"><i class="fa-brands fa-telegram"></i></a>
                        <a href="#" class="w-9 h-9 rounded-xl bg-slate-800 hover:bg-blue-600 flex items-center justify-center"><i class="fa-brands fa-x-twitter"></i></a>
                    </div>
                </div>

                <div>
                    <h4 class="text-white font-extrabold text-sm mb-4 border-r-2 border-blue-500 pr-2">دسته‌بندی‌ها</h4>
                    <ul class="space-y-2 text-xs text-slate-400">
                        <li><a href="products.php" class="hover:text-blue-400">گوشی‌های اپل (آیفون)</a></li>
                        <li><a href="products.php" class="hover:text-blue-400">گوشی‌های سامسونگ</a></li>
                        <li><a href="products.php" class="hover:text-blue-400">گوشی‌های شیائومی</a></li>
                        <li><a href="products.php" class="hover:text-blue-400">ساعت‌های هوشمند</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-white font-extrabold text-sm mb-4 border-r-2 border-blue-500 pr-2">خدمات مشتریان</h4>
                    <ul class="space-y-2 text-xs text-slate-400">
                        <li><a href="#" class="hover:text-blue-400">شرایط بازگشت کالا</a></li>
                        <li><a href="#" class="hover:text-blue-400">استعلام رجیستری</a></li>
                        <li><a href="#" class="hover:text-blue-400">شیوه‌های ارسال</a></li>
                        <li><a href="contact.php" class="hover:text-blue-400">تماس با ما</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-white font-extrabold text-sm mb-4 border-r-2 border-blue-500 pr-2">عضویت در خبرنامه</h4>
                    <form onsubmit="event.preventDefault();" class="relative mb-4">
                        <input type="email" placeholder="آدرس ایمیل..." required 
                               class="w-full bg-slate-800 text-white text-xs px-3 py-2.5 rounded-xl border border-slate-700 focus:outline-none focus:border-blue-500">
                        <button type="submit" class="absolute left-1 top-1 bottom-1 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold px-3 rounded-lg">ثبت</button>
                    </form>
                </div>
            </div>

            <div class="border-t border-slate-800 pt-6 text-center text-xs text-slate-500">
                <p>تمامی حقوق این سایت متعلق به «<?= htmlspecialchars(SITE_NAME) ?>» می‌باشد. ۱۴۰۳ ©</p>
            </div>
        </div>
    </footer>

</body>
</html>