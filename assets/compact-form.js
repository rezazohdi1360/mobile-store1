/* فشرده‌سازی چک‌باکس‌های فرم‌های بزرگ: به‌جای هزاران فیلد، یک فیلد JSON می‌فرستد (دور زدن max_input_vars) */
document.addEventListener('submit', function (e) {
    if (e.defaultPrevented) return;
    var f = e.target, boxes = f.querySelectorAll('input[data-compact]');
    if (!boxes.length) return;
    var groups = {};
    boxes.forEach(function (c) {
        var n = c.getAttribute('data-compact');
        (groups[n] = groups[n] || []);
        if (c.checked && !c.disabled) groups[n].push(c.value);
        c.removeAttribute('name');
    });
    Object.keys(groups).forEach(function (n) {
        var h = document.createElement('input');
        h.type = 'hidden'; h.name = n + '_json'; h.value = JSON.stringify(groups[n]);
        f.appendChild(h);
    });
}, false);
