# OptimizadorPro

Plugin de optimización avanzada para WordPress que mejora significativamente la velocidad de carga de tu sitio web.

## 🚀 Características

### CSS Optimization
- **Minificación y Combinación**: Reduce el tamaño y número de archivos CSS
- **Exclusiones Granulares**: Control total sobre qué archivos optimizar
- **Cache Inteligente**: Sistema de caché basado en modificación de archivos

### JavaScript Optimization
- **Minificación y Combinación**: Reduce el tamaño y número de archivos JS
- **Defer JavaScript**: Previene el bloqueo del renderizado
- **Smart jQuery Dequeue**: Desencola jQuery de forma inteligente cuando es seguro
- **Protección de Scripts Críticos**: Protege automáticamente jQuery y scripts del admin

### Media Optimization
- **LazyLoad Avanzado**: Carga diferida para imágenes e iframes
- **IntersectionObserver**: Usa APIs modernas del navegador
- **Fallback Automático**: Soporte para navegadores antiguos
- **Placeholder SVG**: Evita saltos de layout

### Características Avanzadas
- **Detección Automática**: Analiza el HTML para detectar dependencias
- **Exclusiones Inteligentes**: Sistema granular de exclusiones
- **Configuración por Contexto**: Diferentes configuraciones para usuarios logueados
- **Panel de Administración**: Interfaz intuitiva con pestañas
- **Gestión de Caché**: Limpieza automática y manual de caché

## 📋 Requisitos

- WordPress 5.0+
- PHP 7.4+
- Permisos de escritura en `/wp-content/cache/`

## 🛠 Instalación

1. Sube el plugin a `/wp-content/plugins/optimizador-pro/`
2. Ejecuta `composer install` en el directorio del plugin
3. Activa el plugin desde el panel de WordPress
4. Ve a **Ajustes > OptimizadorPro** para configurar

## ⚙️ Configuración

### CSS Settings
- **Minify & Combine CSS**: Activa la optimización de CSS
- **CSS Exclusions**: Lista de archivos a excluir (uno por línea)

### JavaScript Settings
- **Minify & Combine JS**: Activa la optimización de JavaScript
- **Defer JavaScript**: Añade atributo defer a los scripts
- **Smart jQuery Dequeue**: Permite desencolar jQuery de forma segura
- **JS Exclusions**: Lista de archivos a excluir de minificación
- **Defer JS Exclusions**: Lista de archivos a excluir de defer

### Media Settings
- **Enable LazyLoad**: Activa la carga diferida de medios
- **LazyLoad Exclusions**: Lista de elementos a excluir

### General Settings
- **Excluded Pages**: URLs a excluir de todas las optimizaciones
- **Optimize for Logged Users**: Aplica optimizaciones a usuarios logueados

## 🏗 Arquitectura

OptimizadorPro usa una arquitectura modular basada en:

- **Service Providers**: Registran servicios en el contenedor DI
- **Optimizers**: Contienen la lógica pura de optimización
- **Subscribers**: Se conectan a los hooks de WordPress
- **DI Container**: Gestiona las dependencias usando League Container

### Estructura de Archivos

```
/optimizador-pro
├── inc/
│   ├── Core/                    # Núcleo del plugin
│   ├── Engine/                  # Motores de optimización
│   │   ├── Optimization/        # CSS, JS, Defer
│   │   ├── Media/              # LazyLoad
│   │   └── Cache/              # Gestión de caché
│   ├── Admin/                  # Panel de administración
│   └── Common/                 # Componentes compartidos
├── assets/                     # CSS y JS del admin
└── vendor/                     # Dependencias de Composer
```

## 🔧 Desarrollo

### Dependencias
- `league/container`: Inyección de dependencias
- `matthiasmullie/minify`: Minificación de CSS y JS

### Extensión
El plugin está diseñado para ser fácilmente extensible:

1. Crea un nuevo `Optimizer` con tu lógica
2. Registra el servicio en un `ServiceProvider`
3. Crea un `Subscriber` para conectar con WordPress
4. Añade la configuración al panel de admin

## 📊 Rendimiento

OptimizadorPro puede mejorar significativamente el rendimiento:

- **Reducción de HTTP requests**: Hasta 80% menos peticiones
- **Reducción de tamaño**: 30-60% menos bytes transferidos
- **Mejora en Core Web Vitals**: Especialmente LCP y FCP
- **LazyLoad**: Reduce la carga inicial de la página

## 🛡 Seguridad

- Validación de todas las entradas del usuario
- Sanitización de opciones
- Verificación de permisos
- Protección contra inyección de código

## 🐛 Debugging

Para debuggear problemas:

1. Desactiva las optimizaciones una por una
2. Revisa los logs de PHP para errores
3. Usa las exclusiones para aislar problemas
4. Limpia la caché después de cambios

## 📝 Changelog

### 1.0.0
- Lanzamiento inicial
- Optimización de CSS y JS
- LazyLoad para medios
- Defer JavaScript
- Smart jQuery Dequeue
- Panel de administración completo

## 🤝 Contribuir

Las contribuciones son bienvenidas. Por favor:

1. Fork el repositorio
2. Crea una rama para tu feature
3. Sigue los estándares de código de WordPress
4. Añade tests si es posible
5. Envía un pull request

## 📄 Licencia

GPL v2 or later

## 🆘 Soporte

Para soporte técnico:
- Revisa la documentación
- Busca en issues existentes
- Crea un nuevo issue con detalles completos
