# Plano técnico da prova Fiolux

## Base e isolamento

Fork: https://github.com/mfa326/snipe-it. O fork já existia na conta e seu pai foi confirmado como grokability/snipe-it. Branch local: `fiolux/poc-notebook`. Base: tag remota `v8.8.3`, commit `621e30486a59304f2a5288fb62c4aa91b08e0a84`, confirmada por `git ls-remote` em 30/10/2026.

O usuário informou instalação com imagem Docker oficial na versão 8.8.3. O digest daquela instalação não foi fornecido nem inspecionado; não afirmar equivalência de imagem somente pelo texto de versão. A prova utiliza imagem oficial versionada como base, MariaDB 11.4.7, projeto Compose próprio, volumes próprios, porta local 3041 e rede interna. Não usar credenciais ou dados da instalação existente.

Os documentos de referência são preservados localmente. Como o fork é público, não publicar automaticamente os documentos internos nem dados de acesso. Instruções históricas presentes nos documentos não autorizam intervenções na instalação existente.

## Arquitetura inspecionada

- UI em Blade/Bootstrap, rotas web por entidade e API separada.
- `AssetCheckoutController` utiliza `Asset::checkOut`; o modelo dispara `CheckoutableCheckedOut` e o `LogListener` cria o histórico.
- `AssetCheckinController` altera alocação e status e dispara `CheckoutableCheckedIn`. Não existe um único método de check-in no modelo compartilhado por todos os caminhos.
- Políticas existentes verificam permissões de usuário; o `Gate::before` permite superusuários. A regra documental deve ser uma invariante de processo, não somente uma permissão ou botão.
- Uploads privados usam o disco local sob `storage`; no Docker oficial, `private_uploads` aponta para o volume de dados.
- TCPDF já consta no `composer.lock`; reutilizar a dependência, sem adicionar infraestrutura de PDF.
- Migrations existentes usam IDs simples sem constraints de chave estrangeira. Testes PHPUnit herdam isolamento e configuração de `Tests\TestCase`.

## Implementação incremental

1. Ambiente reproduzível e configuração local sem notificações externas. Banco de testes separado do banco de demonstração.
2. Tabelas próprias para notebooks abrangidos, ciclos, conferências integrais, avaliações técnicas e documentos. Perguntas e respostas são preservadas como retrato versionado.
3. Operações transacionais com bloqueio por ativo e chave de idempotência, relacionando explicitamente conferência e ID da ação nativa. Não procurar ações por proximidade de horário.
4. Proteção no servidor contra movimentação nativa sem conferência para os ativos abrangidos. Inspecionar também operações em lote e atualizações que usam Query Builder, pois observers de Eloquent não cobrem esses caminhos.
5. Tela integrada acessível pelo ativo, pelo colaborador e pelos formulários de movimentação. Notebook é o único modelo da prova. RH recebe fisicamente; TI avalia posteriormente.
6. PDF derivado do registro concluído, upload privado com versões e estados de pendência/falha separados. Assinatura pendente bloqueia nova entrega, sem impedir o recebimento físico. Upload não verifica autenticidade.
7. Dois ciclos e testes de integridade, autorização, falhas e persistência. Classificar cada cenário conforme evidência; ensaios caros podem ficar para a etapa seguinte, com impacto registrado.

## Pontos de alteração previstos

Código novo em subpastas próprias das áreas existentes (`Actions`, `Models`, `Policies`, `Observers`, `Http`, `Console`, views, traduções, migrations e testes). Alterações originais previstas: registro do observer/policy, inclusão das rotas e permissões e pontos de acesso em páginas existentes. Evitar dependências novas, alterações em vendor e reformulação global dos controladores.

## IT-Tools

Revisão preliminar dos módulos PDF e assinatura na branch main em 29/10/2026: o primeiro usa impressão do navegador; o segundo gera imagem JPEG encapsulada em PDF e anexa por usuário/ativo. Foi encontrado fallback de conteúdo PDF vazio após falha de geração em `modules/sign/module.php`. O repositório declara GPL v3 na licença raiz; isso não substitui a verificação de direitos e avisos de cada arquivo a reaproveitar.

Decisão inicial: não importar código. Usar como referência de interação/layout e reutilizar TCPDF já presente no Snipe-IT. Assinatura desenhada permanece fora da obrigação. MDM, etiquetas, e-mail e inventário não serão importados. Não foi realizada auditoria completa de segurança ou licença por arquivo do complemento.
