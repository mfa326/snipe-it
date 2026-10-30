# Execução da prova de conceito Fiolux

Esta prova estende o Snipe-IT 8.8.3 para validar conferências de notebook por ciclo. Use somente dados fictícios. A instalação existente não foi modificada. Os documentos internos recebidos permanecem locais em `docs/referencias`, fora do fork público.

## Preparar uma cópia nova

Requisitos: Git, Docker Desktop com containers Linux, Docker Compose e PowerShell 7.4 ou posterior. Não copiar banco, volumes, tokens ou configuração da instalação existente.

```powershell
git clone --branch fiolux/poc-notebook https://github.com/mfa326/snipe-it.git
cd snipe-it
pwsh -NoProfile -File docker/fiolux/Initialize-Proof.ps1
docker compose -f compose.fiolux.yml build app
docker compose -f compose.fiolux.yml up -d --wait --wait-timeout 300
docker compose -f compose.fiolux.yml exec -T --user docker app php artisan fiolux:prepare --demonstrate --no-interaction
```

O startup executa as migrations reproduzíveis no banco dedicado. O comando `up --wait` aguarda o healthcheck HTTP antes da preparação dos dados; a primeira inicialização pode levar alguns minutos. O código fica montado do checkout; não remover a pasta enquanto usar a prova. A imagem contém as dependências de desenvolvimento do `composer.lock`, para executar testes, e não constitui uma imagem de produção.

Acesso: http://localhost:3041/fiolux. Contas: `teste_fiolux_ti`, `teste_fiolux_rh`, `teste_fiolux_gestao` e `teste_fiolux_admin`. A senha fictícia de demonstração é gerada em `FIOLUX_DEMO_PASSWORD` no `.env` local e não é publicada. A e B são colaboradores fictícios sem login ativo. O comando de preparação preserva os registros já existentes; `--demonstrate` cria os dois ciclos somente se o notebook ainda não tiver ciclos.

O inicializador recusa sobrescrever `.env` ou `.env.testing`. Guarde esses arquivos de forma protegida. A perda da chave `APP_KEY`, das chaves OAuth ou das credenciais pode impedir a recuperação correta do ambiente.

## Isolamento

Projeto Compose `teste-fiolux-snipeit`, banco `teste_fiolux_poc`, volumes próprios, porta HTTP limitada a `127.0.0.1:3041`. MariaDB não publica porta e utiliza rede interna. O aplicativo também possui uma rede de acesso local para publicar HTTP. Notificações usam mailer `array`; usuários fictícios não têm e-mail, webhooks ficam vazios e o startup próprio não inicia o agendador do produto. Não há credenciais da instalação existente.

O recurso só é registrado com `FIOLUX_ENABLED=true`. Os ativos abrangidos são cadastrados na tabela própria `fiolux_notebooks`; a preparação inclui um único notebook. Não transformar o prefixo do nome em mecanismo de autorização ou identificação de ciclo.

Mantenha o recurso habilitado enquanto operar os notebooks abrangidos. Desabilitar a flag remove também os bloqueios do fluxo nativo; isso não desfaz registros e exige um plano de transição, não serve como rollback operacional.

## Operação

1. Abra Conferências Fiolux pela página do ativo ou do colaborador. Os formulários nativos de entrega/devolução dos notebooks abrangidos redirecionam para a conferência.
2. TI escolhe o colaborador, responde às quatro perguntas, informa motivos quando exigidos e confirma explicitamente a entrega. Respostas anteriores não são preenchidas como nova conferência.
3. No ciclo, gere o PDF, imprima se necessário e anexe uma cópia fictícia assinada. Cada geração/envio cria registro e caminho novos.
4. A conferência documental exige confirmação separada de identificação, legibilidade e presença de assinaturas. Essa confirmação regulariza a pendência operacional; não é verificação de autenticidade. A advertência continua visível.
5. RH consulta a entrega original, preenche a devolução física e confirma o recebimento. O equipamento passa para status não liberado.
6. TI registra posteriormente a avaliação técnica. Resultados diferentes de disponível mantêm o ativo no status nativo pendente; a classificação detalhada permanece no registro técnico.
7. Nova entrega exige avaliação disponível e cópias de entrega e devolução conferidas, presentes e íntegras. Receber fisicamente e avaliar tecnicamente continuam possíveis com assinatura pendente.

Conferências e avaliações concluídas não possuem edição ou exclusão. Rascunhos e interface de retificação ficam para uma etapa posterior; enquanto isso, não corrigir o histórico diretamente no banco. Os PDFs de exemplo são fictícios e não substituem um termo de responsabilidade validado pela empresa.

Execute comandos que geram documentos com `--user docker`, o mesmo usuário do Apache. Isso evita que arquivos privados gerados por root fiquem ilegíveis para a aplicação. Após editar PHP ou views no checkout, reinicie apenas o container da prova para atualizar os caches do PHP.

## Testes

```powershell
pwsh -NoProfile -File docker/fiolux/Test-Proof.ps1
```

O script força o banco `teste_fiolux_tests`, separado da demonstração, e serializa suas próprias execuções. Não executar outras suítes PHPUnit simultaneamente no mesmo banco. Os testes usam MariaDB e o isolamento de `Tests\TestCase`. O produto representa erros de validação JSON com HTTP 200 e `status: error`; as verificações consideram o envelope e a ausência de gravações.

A suíte Fiolux é opcional quando o recurso está desativado, para preservar a execução padrão do produto. `Test-Proof.ps1` habilita explicitamente o recurso; se estiver habilitado com outro banco, a suíte recusa iniciar. Integrar essa execução dedicada ao CI é uma pendência para a próxima etapa; o CI padrão não comprova os testes Fiolux quando os apresenta como ignorados.

Para testar duas requisições simultâneas de entrega, após concluir e regularizar os ciclos anteriores:

```powershell
pwsh -NoProfile -File docker/fiolux/Test-Concurrency.ps1
```

Esse ensaio grava uma entrega fictícia adicional, verifica IDs e contagens e deixa o ciclo aberto para a devolução pela interface. Não executá-lo em outra instalação. O worker recusa bancos diferentes de `teste_fiolux_poc` e notificações externas habilitadas.

## Backup e recuperação

Com operações da prova interrompidas, gere uma cópia do banco e dos documentos privados:

```powershell
docker compose -f compose.fiolux.yml exec -T app sh docker/fiolux/backup.sh
```

O comando informa a pasta criada em `/var/lib/snipeit/dumps/TESTE-FIOLUX-...`. Copie-a para um local protegido fora dos volumes usando `docker cp`. Guarde também `.env`, `.env.testing`, o commit do código e `/var/lib/snipeit/keys`. Não publicar dumps, chaves ou configuração no repositório. A pausa operacional é necessária porque banco e arquivos não são copiados atomicamente.

Para recuperar, prepare uma cópia descartável com outro nome de projeto Compose, outra porta e volumes vazios. Use a mesma versão do código e a `APP_KEY` preservada. Importe `database.sql` apenas no banco dessa cópia, extraia `private-uploads.tgz` em `/var/lib/snipeit/data` e restaure as chaves em `/var/lib/snipeit/keys`. Corrija propriedade para `docker:staff`, reinicie a cópia e compare contagens de ciclos/conferências, SHA-256 e downloads autenticados. Essa receita não autoriza restauração sobre a instalação existente. O ensaio completo de restauração deve ser classificado separadamente no relatório.

## Atualização do fork

Base Git validada: `621e30486a59304f2a5288fb62c4aa91b08e0a84`. A imagem oficial de referência está fixada por digest no `Dockerfile.fiolux`. Em 30/10/2026, a API de releases do projeto ainda informava `v8.8.3` como última release; não havia release posterior estável para ensaiar.

Quando houver candidata, confirme a tag e os requisitos, busque o histórico completo se o checkout for raso e faça o merge/rebase em branch e ambiente descartáveis. Revise todos os arquivos originais relacionados no relatório. Execute a suíte Fiolux, os fluxos nativos de ativos não abrangidos, os ensaios concorrentes, migrations a partir de banco novo e atualização de uma cópia do banco anterior. Compare PDFs, permissões, logs e bloqueios. Não declarar compatibilidade apenas pela ausência de conflito Git.

Não alterar `vendor`, não executar `composer update` para contornar incompatibilidade e não remover volumes como procedimento de atualização. Ajustes de produção, retenção, alçadas, autenticação definitiva e operação real dependem das próximas validações.
