# Subir a demonstração Fiolux a partir do Git

## Instalação no servidor

Requisitos: Git, Bash, Docker com containers Linux e plugin Docker Compose com suporte a `up --wait`. Não é necessário instalar PHP, Composer, Node ou MariaDB no host.

Use uma pasta nova, separada da instalação existente. Substitua `IP-DO-SERVIDOR` pelo endereço que os participantes usarão no navegador:

```bash
git clone --branch fiolux/poc-notebook https://github.com/mfa326/snipe-it.git fiolux-demo
cd fiolux-demo
bash docker/fiolux/subir-demo.sh --url http://IP-DO-SERVIDOR:3041
```

O script gera o `.env` privado com chaves e senhas aleatórias, constrói o aplicativo a partir do Git e sobe o Compose. Na primeira execução, aplica migrations e prepara automaticamente os dados. A primeira construção pode demorar pelo download das imagens oficiais. Nenhuma imagem da Fiolux precisa ser enviada a um registry ou copiada manualmente.

O projeto é `teste-fiolux-snipeit`, com banco e volumes próprios. MariaDB não publica porta. Para URL de rede, a porta 3041 fica acessível pela interface do servidor; a instalação existente na porta 3040 permanece separada. Verifique que a porta escolhida está livre e acessível aos participantes. O script não altera firewall ou proxy.

Para alterar a porta, use também `--port 3051` e informe essa porta em `--url`. Para escutar somente em loopback, use `--bind 127.0.0.1`. A URL deve conter apenas protocolo, host e porta; não incluir caminho ou credenciais.

Se preferir executar o Compose manualmente:

```bash
bash docker/fiolux/subir-demo.sh --url http://IP-DO-SERVIDOR:3041 --config-only
docker compose -f compose.fiolux.yml up -d --build --wait --wait-timeout 300
```

Uma configuração existente é preservada. O script recusa um `.env` que não corresponda ao banco fictício ou que habilite e-mail externo. Não copie configuração, token, banco ou volumes da instalação real. Guarde o `.env` com acesso restrito e não o publique.

## Acessos e arquivos para a reunião

Abra `http://IP-DO-SERVIDOR:3041/fiolux-demo/`. Essa página contém os atalhos e quatro imagens para baixar, identificadas **ASSINATURA SIMULADA**. Os arquivos também estão no Git em `public/fiolux-demo`.

| Perfil | Usuário | Uso |
|---|---|---|
| TI | `teste_fiolux_ti` | Entrega, conferências, documentos e avaliações técnicas |
| RH | `teste_fiolux_rh` | Consulta, devolução física e documentos, sem administração global |
| Gestão | `teste_fiolux_gestao` | Consulta |
| Administração fictícia | `teste_fiolux_admin` | Apenas preparação e suporte da prova |

A senha das contas fictícias está em `FIOLUX_DEMO_PASSWORD` no `.env` gerado no servidor. Entregue-a aos participantes por meio interno. Não há senha embutida no código, relatório ou página pública. A e B são colaboradores fictícios sem login ativo.

## Estado inicial do roteiro

- **Notebook da reunião:** `TESTE FIOLUX DEMO NB-001`, disponível, sem alocação e sem ciclos anteriores.
- **Colaboradores:** `TESTE FIOLUX DEMO 001 A` e `TESTE FIOLUX DEMO 001 B`.
- **Localização:** `TESTE FIOLUX Sala de Demonstração`.
- **Histórico anterior:** outro notebook, `TESTE FIOLUX NB-001`, contém os ciclos da prova. Na instalação nova são preparados dois ciclos; no ambiente local os três ciclos existentes foram preservados.
- A preparação não executa a entrega ou a devolução do notebook da reunião. Esses passos ficam para TI e RH durante a demonstração.

| Conferência | Tela | Carcaça | Carregador | Cabo | Observações sugeridas |
|---|---|---|---|---|---|
| Entrega A | Conforme | Avaria | Presente | Presente | Risco superficial preexistente na tampa |
| Devolução A | Avaria | Avaria | Ausente | Presente | Trinca na tela; mesmo risco na carcaça, sem alteração aparente |
| Entrega B | Conforme | Avaria | Presente | Presente | Após reparo da tela e fornecimento de carregador substituto simulados pela TI |
| Devolução B | Conforme | Avaria | Presente | Presente | Mesmas condições da entrega B; conferir novamente todos os itens |

Os exemplos B são uma sugestão para a conclusão técnica simulada. O carregador substituto não representa devolução posterior do carregador de A. O registro de ausência do ciclo A permanece íntegro. Se o grupo escolher outras condições, adapte a cópia fictícia antes de anexá-la.

Quando houver avaria ou ausência, preencha o campo de motivo além das observações pertinentes. Na carcaça, descreva o risco preexistente na entrega e confirme sua permanência na devolução; a resposta Avaria permanece igual, enquanto tela e carregador mostram as diferenças.

Na entrega A, gere o PDF, anexe a imagem correspondente e faça a confirmação de conferência documental. Na devolução A, gere o PDF, mas mantenha sua cópia assinada pendente até demonstrar o bloqueio. Depois da avaliação de manutenção/preparação e da liberação técnica pela TI, a nova entrega deve continuar bloqueada. Anexe e confira a cópia da devolução A para regularizar e seguir para B. O upload sozinho não encerra a pendência nem comprova autenticidade.

Retificação, exceção autorizada ao bloqueio e controle da devolução posterior do carregador continuam como discussão do roteiro, sem funcionalidade adicional implementada nesta preparação. A comparação destaca diferenças de respostas; o detalhe da trinca e do risco preexistente aparece nos motivos e observações.

## Repetir, atualizar e verificar

Reexecutar o script não apaga nem reinicia ciclos. Para uma nova reunião, crie outro kit fictício e use os nomes e links informados no terminal:

```bash
docker compose -f compose.fiolux.yml exec -T --user docker app php artisan fiolux:meeting --next
```

Para atualizar a branch e reconstruir a aplicação, mantendo os volumes:

```bash
git pull --ff-only
bash docker/fiolux/subir-demo.sh
```

O código está dentro da imagem construída pelo Compose. Alterações no Git só chegam ao aplicativo depois de reconstruir/recriar o container. Não há bind mount do código, vendor ou cache do Windows. Configuração e views são cacheadas, e OPcache está habilitado. Banco, uploads privados e chaves permanecem nos volumes. Não usar remoção de volumes como atualização ou reinício de demonstração.

```bash
docker compose -f compose.fiolux.yml ps
docker compose -f compose.fiolux.yml logs --tail 80 app
```

O Compose de desenvolvimento foi separado em `compose.fiolux.dev.yml`, projeto `teste-fiolux-snipeit-dev`, porta local 3042. Ele usa dependências de desenvolvimento e bind mount para editar/testar código; não é a configuração indicada para a reunião. Para testes automatizados, suba esse Compose e execute `docker/fiolux/Test-Proof.ps1` em PowerShell 7.4. Para ensaio concorrente, prepare primeiro os dados fictícios nesse ambiente de desenvolvimento.
