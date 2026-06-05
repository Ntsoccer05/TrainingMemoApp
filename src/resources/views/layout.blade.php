<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title>トレメモ</title>
  <!-- Fonts -->
  <link href="https://fonts.bunny.net/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://use.fontawesome.com/releases/v6.2.0/css/all.css" rel="stylesheet">
  <link rel="shortcut icon" type="image/x-icon" href="https://training-memo.s3.ap-northeast-1.amazonaws.com/icon.ico">

  <meta name="csrf-token" content="{{ csrf_token() }}">

  @vite(['resources/css/app.css', 'resources/js/app.ts'])
</head>

<body>
  <div id="app" class="h-screen"></div>
</body>

</html>