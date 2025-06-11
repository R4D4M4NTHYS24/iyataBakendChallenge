<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenAI;

class AsistenteProyectoController extends Controller
{
    /**
     * Recibe datos de un plan de proyecto y devuelve un diagnóstico/insights desde OpenAI.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function validarPlan(Request $request)
    {
    $clave = $request->header('X-Access-Key'); // <- PRIMERO definirla
\Log::info("Header clave: " . $clave);    // <- LUEGO loguear
\Log::info("ENV clave esperada: " . config('services.access_key'));

if ($clave !== config('services.access_key')) {
    return response()->json([
        'error' => 'Acceso no autorizado',
        'message' => 'Clave de acceso inválida',
    ], 401);
}


        // 1) Validación de los datos entrantes
        $validator = Validator::make($request->all(), [
            'nombre'                 => 'required|string',
            'descripcion'            => 'required|string',
            'metodologia'            => 'required|string',
            'presupuesto'            => 'required|numeric',
            'fecha_inicio'           => 'required|date',
            'fecha_fin'              => 'required|date|after_or_equal:fecha_inicio',
            'tamano_equipo'          => 'required|string',
            'plazo_dias'             => 'required|integer',
            'nivel_riesgo_aceptable'=> 'required|integer|min:1|max:5',
            'prioridad'              => 'required|in:Alta,Media,Baja',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error'    => 'Datos inválidos',
                'mensajes' => $validator->errors(),
            ], 422);
        }

        // 2) Obtener valores del request
        $nombre       = $request->input('nombre');
        $descripcion  = $request->input('descripcion');
        $metodologia  = $request->input('metodologia');
        $presupuesto  = $request->input('presupuesto');
        $fechaInicio  = $request->input('fecha_inicio');
        $fechaFin     = $request->input('fecha_fin');
        $tamanoEquipo = $request->input('tamano_equipo');
        $plazoDias    = $request->input('plazo_dias');
        $nivelRiesgo  = $request->input('nivel_riesgo_aceptable');
        $prioridad    = $request->input('prioridad');

        // 3) Construir prompt mejorado
        $prompt = <<<EOT
### Contexto
Eres un PM senior que evalúa planes de proyecto.

### Instrucciones
1. Analiza el plan que te proporcionaré.
2. Identifica:
   - 3 principales riesgos + criticidad (1-5).
   - 3 oportunidades + beneficio (1-5).
   - 3 recomendaciones con costo estimado en días.
3. Devuelve exactamente lo siguiente:
   - Primero: un diagnóstico completo en Markdown con los riesgos, oportunidades y recomendaciones.
   - Segundo: un bloque JSON al final, encerrado entre ```json y ```, sin ninguna explicación ni texto adicional antes ni después.
   - Este bloque JSON debe seguir la siguiente estructura y ser estrictamente válido para poder ser procesado por una API:


\`\`\`json
{
  "riesgos": [
    { "descripcion": "...", "criticidad": 1-5 }
  ],
  "oportunidades": [
    { "descripcion": "...", "beneficio": 1-5 }
  ],
  "recomendaciones": [
    { "descripcion": "...", "costo_estimado_dias": 3 }
  ],
  "score_global": número entre 0 y 100 que representa la calidad del plan de proyecto.

Antes de generar el JSON, revisa con cuidado si hay 1, 2 o 3 riesgos con criticidad 4 o 5. Cada uno debe restar 10 puntos.
No ignores esta regla. Si hay 3 riesgos críticos, deben restarse 30 puntos, no 20.
Antes de calcular el score, debes:
- Contar cuántos riesgos tienen criticidad 4 o 5.
- Contar cuántas recomendaciones superan 4 días.
- Estas cuentas deben reflejarse en los descuentos con exactitud.
Debe calcularse así:
- Parte de un máximo de 100 puntos.
- Resta:
  - 10 puntos por cada riesgo identificado con criticidad 4 o 5.
  - 7 puntos por cada recomendación que implique 5 o más días de mitigación.
  - 5 puntos si el plazo es inferior a 30 días en proyectos con IA.
  - 5 puntos si el equipo es insuficiente (menos de 3 perfiles técnicos).
  - 5 puntos si el riesgo aceptable declarado es bajo (1 o 2) pero los riesgos reales son críticos.
- Suma:
  - 5 puntos por cada oportunidad con beneficio 4 o 5.
  - 5 puntos si hay más de una recomendación concreta y realizable.

El resultado debe ser redondeado al entero más cercano, y nunca debe ser menor que 0 ni mayor que 100.

Ejemplo esperado:
- Si hay 3 riesgos críticos y 2 recomendaciones largas => se descuentan 44 puntos.
- Si hay 2 oportunidades fuertes => se suman 10 puntos.
- Resultado final: 100 - 44 + 10 = 66
}
\`\`\`

### Plan
- Nombre: {$nombre}
- Descripción: {$descripcion}
- Metodología: {$metodologia}
- Presupuesto: {$presupuesto} COP
- Fecha inicio: {$fechaInicio}
- Fecha fin: {$fechaFin}
- Tamaño equipo: {$tamanoEquipo}
- Plazo deseado: {$plazoDias} días
- Nivel de riesgo aceptable: {$nivelRiesgo}
- Prioridad declarada: {$prioridad}
EOT;


        // 4) Instanciar cliente OpenAI
        $openai = OpenAI::client(config('services.openai.key'));

        // 5) Llamada a la API de chat
        $response = $openai->chat()->create([
    'model'       => 'gpt-4o',
    'messages'    => [
        ['role' => 'system', 'content' => 'Eres un asistente experto en gestión de proyectos.'],
        ['role' => 'user', 'content' => $prompt],
    ],
    'max_tokens'  => 2000,
    'temperature' => 0.7,
]);


        // 6) Extraer respuesta y parsear JSON embebido
        $contenido = $response['choices'][0]['message']['content'] ?? 'No se obtuvo respuesta';

        // Extraer el bloque JSON al final del texto
        $jsonMatch = [];
        preg_match('/```json\s*({.+?})\s*```/is', $contenido, $jsonMatch);

        $insights = null;
        if (isset($jsonMatch[1])) {
            try {
                $insights = json_decode($jsonMatch[1], true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                $insights = null;
            }
        }

        // Separar el Markdown del JSON
        // Separar el Markdown del JSON
$diagnosticoMD = $insights ? str_replace($jsonMatch[0], '', $contenido) : $contenido;

if ($insights && isset($insights['score_global'])) {
    $scoreText = "\n\n### ✅ Score Global del Plan: **{$insights['score_global']} / 100**\n";
    $diagnosticoMD = trim($diagnosticoMD) . $scoreText;
}


// Validar que el score_global esté presente
if (!$insights || !isset($insights['score_global'])) {
    return response()->json([
        'error' => 'La respuesta de IA no incluye score_global',
        'diagnosticoMD' => trim($diagnosticoMD),
        'insights' => null,
    ], 500);
}


        // 7) Respuesta final
        return response()->json([
            'diagnosticoMD' => trim($diagnosticoMD),
            'insights'      => $insights,
        ]);
    }
}
