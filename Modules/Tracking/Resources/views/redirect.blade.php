<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Redirecting...</title>
</head>
<body>

<script>
    // تخزين التوكن في localStorage
    const token = "{{ $token }}";
    localStorage.setItem('affiliate_token', token);

    // إعادة التوجيه للصفحة الأصلية
    window.location.href = "{{ $redirectTo }}";
</script>
<script>
    let token = localStorage.getItem("affiliate_token");
    if (token) {
        fetch(window.location.href, {
            method: "GET",
            headers: { "X-Affiliate-Token": token }
        });
    }
</script>

</body>
</html>
