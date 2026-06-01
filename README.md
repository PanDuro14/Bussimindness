# Bussimindness

## Descripción

Bussimindness es una plataforma web orientada a la promoción de pequeños negocios mediante un foro publicitario. El sistema permite a los usuarios registrarse, iniciar sesión, publicar contenido y visualizar publicaciones de otros negocios, facilitando la difusión de productos y servicios.

---

## Tecnologías Utilizadas

- PHP
- MySQL
- JavaScript
- HTML5
- CSS3
- Git
- GitHub

---

## Control de Versiones

Para la gestión del proyecto se utiliza Git como sistema de control de versiones y GitHub como plataforma de alojamiento del repositorio.

### Flujo de Trabajo

Se implementa el modelo **Feature Branch Workflow**, el cual consiste en desarrollar nuevas funcionalidades en ramas independientes antes de integrarlas a la rama principal.

### Estructura de Ramas

- **main**: versión estable del proyecto.
- **develop**: integración de funcionalidades en desarrollo.
- **feature/login**: módulo de autenticación.
- **feature/forum**: sistema de publicaciones.
- **feature/security**: implementación de medidas de seguridad.

### Flujo de Desarrollo

1. Crear una rama feature a partir de develop.
2. Desarrollar la funcionalidad.
3. Realizar commits periódicos.
4. Integrar la rama en develop mediante merge.
5. Realizar pruebas.
6. Integrar develop en main cuando la versión sea estable.

---

## Evidencias de Versionamiento

Ejemplos de commits realizados:

- Initial commit
- Added login module
- Implemented password hashing
- Added forum publication system
- Implemented security enhancements
- Merged feature branches into main

---

## Instalación

1. Clonar el repositorio:

```bash
git clone https://github.com/PanDuro14/Bussimindness.git
```

2. Copiar el proyecto en el directorio de XAMPP:

```text
C:\xampp\htdocs\
```

3. Configurar la base de datos MySQL.

4. Iniciar Apache y MySQL desde XAMPP.

5. Acceder desde el navegador:

```text
http://localhost/Bussimindness
```

---

## Autor

Andrea Cuevas

Proyecto académico desarrollado para la materia de Desarrollo Web Integral.
