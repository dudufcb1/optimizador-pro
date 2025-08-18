# 📦 Instalación de OptimizadorPro

## 🚀 Instalación Rápida

### Opción 1: Desde el ZIP Pre-compilado

1. **Descargar el ZIP**
   - Usa el archivo `dist/optimizador-pro-v1.0.0.zip` ya compilado

2. **Instalar en WordPress**
   - Ve a **WordPress Admin → Plugins → Añadir nuevo**
   - Haz clic en **"Subir plugin"**
   - Selecciona el archivo ZIP
   - Haz clic en **"Instalar ahora"**
   - **Activa el plugin**

3. **Configurar**
   - Ve a **Ajustes → OptimizadorPro**
   - Configura las optimizaciones según tus necesidades

### Opción 2: Compilar desde el Código Fuente

1. **Clonar/Descargar el código**
   ```bash
   # Si tienes git
   git clone [repository-url]
   cd optimizador-pro
   
   # O descargar y extraer el ZIP del código fuente
   ```

2. **Compilar el plugin**
   ```bash
   # En Linux/Mac
   ./build.sh
   
   # En Windows (PowerShell)
   .\build.ps1
   ```

3. **Instalar el ZIP generado**
   - Usa el archivo `dist/optimizador-pro-v1.0.0.zip` generado
   - Sigue los pasos de la Opción 1

## 🔧 Requisitos del Sistema

### WordPress
- **WordPress:** 5.0 o superior
- **PHP:** 7.4 o superior
- **Permisos:** Escritura en `/wp-content/cache/`

### Para Compilación (solo si compilas desde código fuente)
- **Composer:** Para gestión de dependencias
- **PHP CLI:** Para verificación de sintaxis
- **Bash/PowerShell:** Para ejecutar scripts de build

## ⚙️ Configuración Inicial

### 1. Verificar Permisos
Asegúrate de que WordPress pueda escribir en:
```
/wp-content/cache/optimizador-pro/
```

### 2. Configuración Básica Recomendada

#### CSS Optimization
- ✅ **Minify & Combine CSS:** Activar
- **CSS Exclusions:** Dejar vacío inicialmente

#### JavaScript Optimization  
- ✅ **Minify & Combine JS:** Activar
- ✅ **Defer JavaScript:** Activar
- ❌ **Smart jQuery Dequeue:** Desactivar inicialmente (avanzado)
- **JS Exclusions:** Dejar vacío inicialmente

#### Media Optimization
- ✅ **Enable LazyLoad:** Activar
- **LazyLoad Exclusions:** Añadir imágenes del logo/hero si es necesario

#### General Settings
- **Excluded Pages:** Añadir `/wp-admin`, `/wp-login.php`
- ❌ **Optimize for Logged Users:** Desactivar para testing

### 3. Testing Inicial

1. **Activar una optimización a la vez**
2. **Probar el sitio en frontend**
3. **Verificar que no hay errores JavaScript**
4. **Comprobar que las imágenes cargan correctamente**
5. **Usar herramientas como GTmetrix/PageSpeed Insights**

## 🐛 Solución de Problemas

### El plugin no se activa
- Verificar versión de PHP (mínimo 7.4)
- Verificar que Composer instaló las dependencias
- Revisar logs de error de WordPress

### Errores JavaScript después de activar
- Desactivar "Smart jQuery Dequeue"
- Añadir scripts problemáticos a "JS Exclusions"
- Desactivar "Defer JavaScript" temporalmente

### Imágenes no cargan con LazyLoad
- Añadir clases/IDs problemáticos a "LazyLoad Exclusions"
- Verificar que no hay conflictos con otros plugins de LazyLoad

### CSS roto después de minificación
- Añadir archivos problemáticos a "CSS Exclusions"
- Verificar que no hay @import en CSS externos

### Cache no se genera
- Verificar permisos de escritura en `/wp-content/cache/`
- Crear manualmente el directorio si no existe
- Verificar que no hay plugins de caché conflictivos

## 📊 Verificación de Funcionamiento

### 1. Verificar Cache
- Ve a **OptimizadorPro → Tools**
- Verifica que aparecen archivos CSS/JS en el estado del caché

### 2. Inspeccionar HTML
- Ver código fuente de la página
- Buscar archivos combinados: `combined-[hash].css` y `combined-[hash].js`
- Verificar atributos `defer` en scripts
- Verificar `data-src` en imágenes (LazyLoad)

### 3. Herramientas de Testing
- **GTmetrix:** Verificar reducción de requests HTTP
- **PageSpeed Insights:** Mejorar puntuaciones Core Web Vitals
- **DevTools:** Verificar que no hay errores en consola

## 🔄 Actualización

### Desde ZIP Pre-compilado
1. Desactivar el plugin actual
2. Eliminar la carpeta del plugin
3. Instalar la nueva versión
4. Reactivar y verificar configuración

### Desde Código Fuente
1. Actualizar el código
2. Ejecutar `./build.sh` para generar nuevo ZIP
3. Seguir proceso de actualización normal

## 📞 Soporte

### Logs de Error
Revisar logs en:
- `/wp-content/debug.log` (si WP_DEBUG está activado)
- Logs del servidor web
- Consola del navegador (F12)

### Información para Soporte
Al reportar problemas, incluir:
- Versión de WordPress
- Versión de PHP  
- Lista de plugins activos
- Tema utilizado
- Configuración de OptimizadorPro
- Mensajes de error específicos

## 🎯 Mejores Prácticas

1. **Hacer backup** antes de activar optimizaciones
2. **Probar en staging** antes de producción
3. **Activar optimizaciones gradualmente**
4. **Monitorear métricas** de rendimiento
5. **Mantener exclusiones** actualizadas
6. **Limpiar caché** después de cambios importantes
