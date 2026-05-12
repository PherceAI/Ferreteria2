<?php

return [
    'accepted' => 'El campo :attribute debe ser aceptado.',
    'confirmed' => 'La confirmacion de :attribute no coincide.',
    'current_password' => 'La contrasena actual no es correcta.',
    'email' => 'El campo :attribute debe ser un correo electronico valido.',
    'max' => [
        'string' => 'El campo :attribute no debe ser mayor que :max caracteres.',
    ],
    'min' => [
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
    ],
    'numeric' => 'El campo :attribute debe ser un numero.',
    'password' => [
        'letters' => 'El campo :attribute debe contener al menos una letra.',
        'mixed' => 'El campo :attribute debe contener al menos una letra mayuscula y una minuscula.',
        'numbers' => 'El campo :attribute debe contener al menos un numero.',
        'symbols' => 'El campo :attribute debe contener al menos un simbolo.',
        'uncompromised' => 'La contrasena indicada aparece en una filtracion de datos. Usa otra contrasena.',
    ],
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',
    'unique' => 'El valor de :attribute ya esta registrado.',

    'attributes' => [
        'email' => 'correo electronico',
        'name' => 'nombre completo',
        'password' => 'contrasena',
        'password_confirmation' => 'confirmacion de contrasena',
    ],
];
