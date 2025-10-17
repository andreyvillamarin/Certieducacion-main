<?php
// mock_api.php

// Este archivo simula una API externa para el desarrollo.

header('Content-Type: application/json');

// Datos de ejemplo que simulan la base de datos externa.
$courses_data = [
    [
        'course_id' => 'PROG-101',
        'course_name' => 'Introducción a la Programación con Python',
        'duration' => 40,
        'students' => [
            ['name' => 'Andrey Villamarin', 'identification' => '1075298234'],
            ['name' => 'Jane Doe', 'identification' => '987654321'],
            ['name' => 'Peter Jones', 'identification' => '123123123']
        ]
    ],
    [
        'course_id' => 'DESIGN-202',
        'course_name' => 'Diseño Gráfico para Redes Sociales',
        'duration' => 35,
        'students' => [
            ['name' => 'Emily White', 'identification' => '456456456'],
            ['name' => 'Chris Green', 'identification' => '789789789']
        ]
    ],
    [
        'course_id' => 'WEB-301',
        'course_name' => 'Desarrollo Web Frontend con React',
        'duration' => 80,
        'students' => [
            ['name' => 'Sarah Connor', 'identification' => '111222333'],
            ['name' => 'John Wick', 'identification' => '444555666']
        ]
    ]
];

// Manejar la petición
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Simular la obtención de la lista de cursos
    // En una API real, aquí se haría una consulta a la base de datos.
    
    // Permitir búsqueda simple por término (q)
    if (isset($_GET['q']) && !empty($_GET['q'])) {
        $search_term = strtolower($_GET['q']);
        $filtered_courses = array_filter($courses_data, function($course) use ($search_term) {
            return strpos(strtolower($course['course_name']), $search_term) !== false;
        });
        echo json_encode(array_values($filtered_courses));
    } else {
        echo json_encode($courses_data);
    }

} elseif ($method === 'POST') {
    // Simular la recepción de un certificado en PDF
    // En una API real, aquí se guardaría el archivo en el disco o en un bucket de almacenamiento.

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (isset($data['pdf_content']) && isset($data['student_identification']) && isset($data['course_id'])) {
        // Simulación de guardado
        $filename = 'certificate-' . $data['course_id'] . '-' . $data['student_identification'] . '-' . time() . '.pdf';
        // Aquí no lo guardamos realmente, solo simulamos el éxito.
        // file_put_contents('external_storage/' . $filename, base64_decode($data['pdf_content']));

        echo json_encode([
            'success' => true, 
            'message' => 'Certificado recibido y guardado correctamente.',
            'filename' => $filename
        ]);
    } else {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Datos incompletos. Se requiere pdf_content, student_identification y course_id.']);
    }

} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
}

exit;
?>