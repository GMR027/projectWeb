App: Creacion de programa que permita crear programas de obra de diferentes proyectos
Objetivo: Crear una plataforma donde se puedan crear proyectos y a su vez programas de obra
Rol de agente: Desarrollador web 12 anos de exp

Funcionalidades de la app:
CRUD de proyectos
CRUD de programas de obra

En los programas de obras esta organizado de la siguiente forma:
header: titulo de proyecto, logo, fecha actual
Seccion 1: fecha de inicio, fecha de finalizacion

Tabla
id, tipo, nombre, fechainicio, fechafin, duracion(dias), progreso, status, prioridad, descripcion, restricciones(adicional debe haber un selector con el tipo de restriccion: clima, contrato, ingenieria, rfi, submittal, material, equipo, mano de obra, acceso, otro)

Diagrama de gant
Mostrara de acuerdo al a fecha de inicio y fecha de finalizacion las actividades de forma grafica.

El usuario podra agregar actividades (tipo: si es partida o actividad, esto con la finalidad para poder diferenciar las actividades de cada partida) y estas actividades podran contar con la informacion de la tabla. 

Cuando una actividad este completa, en el grafico de gant se debera mostrar en color verde, amarillo si esta en proceso y gris si no se ha iniciado

cuando haya una restruccion, debera marcar un circulo rojo para indicar que hay restriccion. 

En el diagrama se debera ajustar con respecto a las semanas y su numero de semana. 
Las partidas tendran la posibilidad de poder colocar un backgrund color, y las actividades no. 

Debera haber en ajustes lo siguiente: 
asignacion fecha de descanso, para que no se tome en cuenta'
exportacion: debe permitir exportar el archivo en formato pdf, .mpp y .pod y .xml con la nomenclaruta que escoja el usuario, siempre y cuando al principio vaya con la fecha (Y, m, d) El formato pdf debe ser tamano tabloide para imprimir

ubicacion donde estara el codigo: /home/edgar/Documentos/programacion/PROJECT/PO

Esta informacion debera usarse para despues conectar con otras herramientas que iremos desarrolando. 

Adicional agrega el calendario operativo como esta en las referencias.

stack de tecnologias:
html
css3
sass
php
mysql: la base de datos llamala project


Preferencias estilos:
colores del diseno
usar medidas en rem
uso buenas practicas de maquetacion, uso de flexbox, grid layout
que la web sea responsiva

Estructura de archivos:
carpeta (desing) Hay datos en los estilos y referencias que no estan en nuestro planteamiento, esos ignoralos. Basate en los estilos para poder hacer el proyecto.
Estructura de ficheros mas adecuada para proyectos php
Usar como refencia el codigo de /home/edgar/Documentos/programacion/UpTask_MVC

Si tienes dudas hazme saber y revisa nuevamente el arhivo: OPENCODE.md