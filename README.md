#Тестовый проект для Интерсо/Winbix

## Использованные языки и фреймворки

- PHP + Slim + FluentPDO
- JS + Vue + BootstrapVue

## Требования

- docker
- docker-compose
- make

## Запуск

Создать свою версию .env файла и настроить его, если нужно.

``cp .env.dist .env``

Скачать docker образы и запустить контейнеры их использующие

``make up``

Обработать инструкции composer и создать папку vendor

``make composer-install``


### Основное

После установки проект будет доступен на хостовой машине с указанием порта.

http://localhost:<PORT>

Порт берется из настроек .env


