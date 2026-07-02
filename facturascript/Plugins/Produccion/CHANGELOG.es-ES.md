# Changelog

Todos los cambios notables en este proyecto se documentarán en este archivo.


## [2.0.5] - 2026-06-02
### Corrección de errores
- Corregida impresión de la receta para convertir a string los valores numéricos y evitar incompatibilidad con el plugin PlantillasPDF.


## [2.0.4] - 2026-05-25
### Nuevas funcionalidades y mejoras
- Añadido informe de trazabilidad de números de serie para los productos producidos.
    - En la pestaña de números de serie de la orden de producción, cada fila dispone ahora de un botón de trazabilidad.
    - Al pulsarlo se abre un modal con la trazabilidad completa: datos del producto, información de la orden de producción y la lista de ingredientes con sus números de serie consumidos.
    - Un botón de impresión permite exportar el informe de trazabilidad como documento PDF.


## [2.0.3] - 2026-04-28

### Nuevas funcionalidades y mejoras
- Seleccionar si se desea las columnas de coste al imprimir la receta. Ahora desde el panel de configuración de Producción, se puede marcar o desmarcar si al imprimir la receta deben mostrarse los datos del coste.
- Seleccionar si se puede o no crear recetas con el mismo código. Ahora desde el panel de configuración de Producción, se puede marcar o desmarcar si se permite crear recetas con el mismo código.
- Añadidos datos (usuario, fecha y hora) de fabricación
- Añadidos filtros a la lista de órdenes de producción por usuario y fecha de fabricación.
- Añadido filtro y ordenación a la lista de órdenes de producción por fecha de vencimiento.

- Nueva ventana con la ficha del producto
    - Ahora existe un botón en la lista de ingredientes y productos producidos/a fabricar, en recetas y órdenes de producción, que abre una ventana modal con la ficha del producto seleccionado incluyendo la foto.

- Añadida la posibilidad de indicar el producto principal en la receta
    - Se selecciona el producto principal de la lista de productos producidos.
    - Se muestra el producto principal en los listados de recetas y órdenes de producción.
    - Se puede buscar y filtrar las recetas y órdenes de producción por el producto principal.

- Añadido clonado de órdenes de producción desde la edición de una órden.
    - Al clonar una orden, la nueva orden queda en estado inicial.
    - Al clonar una orden de producción se copian los ingredientes y productos producidos.
    - Al clonar una orden de producción no se copian números de serie.

### Cambios
- Ahora se visualiza el identificador de la receta en las listas.
- Ahora se visualiza la referencia del producto en la lista y edición de los contadores de los números de serie.
- Ahora se visualiza la referencia del producto en la asignación de números de serie en el albarán de cliente.
- Añadida columna con el código de receta en la lista de órdenes de producción.
- Añadida columna con el coste de la receta en la lista de recetas.
- Al imprimir el albarán de cliente con productos con números de serie, se muestra el número de serie asignado a cada producto.
- Al producir una receta o una orden de producción, se permite producir si el producto está marcado como "ventas sin stock".

### Corrección de errores
- Proceso de actualización del settings corregido para compatibilidad con el modelo Settings del core 2025.
- Proceso de actualización de estructura de la base de datos, corregido para evitar duplicar productos producidos.
- Corregido error al crear órdenes de producción cuando no se tenía seleccionada la confirmación automática de las cantidades a preparar.
- Corregido el cálculo de la cantidad en el listado de órdenes de producción.
- Corregido el cálculo del coste de la variante cuando se producen más de un producto. Se reparte el coste por la cantidad producida del producto.
- Al editar un albarán de cliente, no mostraba correctamente los números de serie asignados a los productos.
- Corregido pipe "addStock" para que no añada el stock de todos los productos al producir una orden de receta, sino añada el stock del producto producido indicado.
- Corregido pipe "removeStock" para que no elimine el stock de todos los productos al producir una orden de receta, sino que elimine el stock del ingrediente indicado.


## [2.0.1] - 2025-11-19

### Nuevas funcionalidades y mejoras
- Compatibilidad para producir productos con números de serie.


## [2.0.0] - 2025-10-09

### Cambios
- Actualización del plugin para ser compatible con el core 2025.


## [1.53.0] - 2025-04-14

## Nuevas funcionalidades y mejoras
- Añadido a la configuración del plugin la opción de indicar si se desean decimales en las cantidades al producir.
- Añadida la columna "se vende" en las líneas de recetas para poder indicar si el producto es materia prima o un producto final.
- Ahora se pueden incluir en las recetas archivos adjuntos.
- Sustitución de referencias o productos en las recetas. Ahora se pueden reemplazar (tanto a una receta como a una selección de recetas, como a todas las recetas) una referencia por otra de manera automática.
- Se ha añadido las pestañas de ingredientes y producidos al controlador de listado de recetas y órdenes de producción. Estas nuevas pestañas muestran la lista de materiales utilizados y producidos en las órdenes de producción. También permiten filtrar por distintos campos facilitando la localización de las órdenes.
- Añadido campo de usuario para registrar el usuario que ha realizado la producción. También se ha añadido filtro.
- Búsqueda de órdenes de producción por identificador de la orden.

## Corrección de errores
- División por cero al producir recetas con cantidad a cero.
