```sh
cd apps/screen/tests/evals
# use the right configs in .env
cp .env.example .env
# only evals for prompt 1
npx promptfoo@latest eval --env-file .env -c promptfooconfig.yaml --filter-pattern "prompt01"
# evals for all prompts
npx promptfoo@latest eval --env-file .env -c promptfooconfig.yaml
# view results in web UI
npx promptfoo@latest eval view

```