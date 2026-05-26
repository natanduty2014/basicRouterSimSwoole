import subprocess
import datetime
import time

def fazer_backup_mysql(usuario, senha, banco_de_dados, destino):
    try:
        # Gerar o nome do arquivo de backup com base na data e hora atual
        nome_arquivo_backup = f"backup_{banco_de_dados}_{datetime.datetime.now().strftime('%Y-%m-%d_%H-%M')}.sql.gz"

        # Comando mysqldump para criar o backup e comprimir com gzip
        comando_backup = f"mysqldump -u {usuario} -p{senha} {banco_de_dados} | gzip > {destino}/{nome_arquivo_backup}"

        # Executar o comando de backup e capturar a saída de erro
        subReturn = subprocess.run(comando_backup, shell=True, capture_output=True)
        if subReturn.returncode != 0:
            return True
        else:
            # Verificar se ocorreu algum erro durante o processo
            erro_str = subReturn.stderr.decode()
            if "mysqldump: Got error: 1045" in erro_str:
                print("Erro 1045: Acesso negado. Verifique suas credenciais de usuário e senha.")
                #remover o arquivo de backup criado
                subprocess.run(f"rm {destino}/{nome_arquivo_backup}", shell=True)
            elif "Erro 1045" in erro_str:
                print("Erro 1045: Acesso negado. Verifique suas credenciais de usuário e senha.")
                #remover o arquivo de backup criado
                subprocess.run(f"rm {destino}/{nome_arquivo_backup}", shell=True)
            elif "Unknown database" in erro_str:
                print("Erro 1049: Banco de dados desconhecido. Verifique se o nome do banco de dados está correto.")
                #remover o arquivo de backup criado
                subprocess.run(f"rm {destino}/{nome_arquivo_backup}", shell=True)
            elif subReturn.returncode != 0:
                # Se o retorno for diferente de zero e não for erro 1045 ou 1049, algo deu errado durante o backup
                print("Ocorreu um erro desconhecido durante o backup do banco de dados:")
                print(erro_str)
                #remover o arquivo de backup criado
                subprocess.run(f"rm {destino}/{nome_arquivo_backup}", shell=True)
            else:
                return True
    except subprocess.CalledProcessError as e:
        # Se ocorrer um erro de chamada do subprocesso (por exemplo, comando inválido)
        print(f"Erro de subprocesso ao fazer backup do banco de dados: {e}")
    except Exception as e:
        # Se ocorrer qualquer outro tipo de exceção não prevista
        print(f"Erro ao fazer backup do banco de dados: {e}")

# Definir as credenciais do MySQL e o banco de dados a ser feito o backup
usuario_mysql = "root"
senha_mysql = "my_secret_password"
banco_de_dados_mysql = "app_db"

# Definir o diretório de destino para o backup
diretorio_destino = "/backup"

# Chamar a função para fazer o backup e comprimir como .gz
if fazer_backup_mysql(usuario_mysql, senha_mysql, banco_de_dados_mysql, diretorio_destino) != True:
    print("erro ao fazer backup do banco de dados")
    #exit 
    exit(1)

# Loop principal que executa o backup a cada 24 horas
while True:
    fazer_backup_mysql(usuario_mysql, senha_mysql, banco_de_dados_mysql, diretorio_destino)
    # Esperar 24 horas antes de executar novamente
    time.sleep(24 * 60 * 60)  # 24 horas em segundos
    #1 minuto
    #time.sleep(60)