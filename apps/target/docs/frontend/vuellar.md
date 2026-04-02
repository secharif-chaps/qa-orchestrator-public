# Vuellar (formerly known as Feathers)

This guide covers the way we use Vuellar/Feathers library.

## Git repository

For information, Vuellar Git repository is [here](https://git.mediaspeech.com/galactik/vuellar/).
Current version can be found in package.json.

## Deploy a new version

```bash
docker compose exec pwa yarn add @owlint/feathers-vue@^x.y.zz
```

Pwa container needs to be rebuilt after that.

## Local documentation

The [online documentation](https://feathers-vue.staging.owl-int.com/) may not be up to date. This is why it can be useful to display documentation locally.

First, if you do not have it, clone Vuellar project:

```bash
git clone ssh://git@git.mediaspeech.com:17890/galactik/vuellar.git
```

Then type these commands:

```bash
yarn
yarn storybook
```

Documentation is accessible via [localhost:6006](localhost:6006).
