import spacy
import string 

nlp = spacy.load('fr_core_news_md')

def tokenize(words): 
    words = words.translate(str.maketrans('','',string.punctuation))
    words = words.strip()
    words = words.replace('\n', '')
    return words.split(" ") 
    
def jaccard_similarity(A, B):
    #Find intersection of two sets
    nominator = A.intersection(B)

    #Find union of two sets
    denominator = A.union(B)

    #Take the ratio of sizes
    similarity = len(nominator)/len(denominator)
    
    return similarity

questions = dict()
good_answer = dict()
key = dict()
entire = dict()

for number in range(1, 1209):
    filename = "questions/" + str(number) + ".txt"
    sentence = open(filename, "r")
    questions[number] = sentence.read()

for number in range(1, 51):
    filename = "tests/" + str(number) + ".txt"
    sentence = open(filename, "r")
    good_answer[number] = sentence.read()
    
for number in range(1, 51):
    filename = "tests/" + str(number) + "_key.txt"
    sentence = open(filename, "r")
    key[number] = sentence.read()
    

for number in range(1, 51):
    filename = "tests/" + str(number) + "_entire.txt"
    sentence = open(filename, "r")
    entire[number] = sentence.read()
   
key_rate = dict(); 
for k, v in key.items():
    similarity = 0; 
    answer = ""; 
    setA = tokenize(v)
    for question in questions:

        filename = "lemmatization/" + str(question) + ".txt";
        sentence = open(filename, "r")
        setB = tokenize(sentence.read()) 

        commputesimilarity = jaccard_similarity(set(setA), set(setB))
        
        
        if commputesimilarity > similarity:
            similarity = commputesimilarity
            answer = questions[question] 
            
    if answer == good_answer[k]:
        key_rate[k] = similarity; 

actual = dict()
expected = dict()
entire_rate = dict()
bad_similarity = dict()
for k, v in entire.items():
    similarity = 0; 
    answer = ""; 
    setA = tokenize(v)
    for question in questions:

        filename = "lemmatization/" + str(question) + ".txt";
        sentence = open(filename, "r")
        setB = tokenize(sentence.read()) 

        commputesimilarity = jaccard_similarity(set(setA), set(setB))
        
        
        if commputesimilarity > similarity:
            similarity = commputesimilarity
            answer = questions[question] 
            
    if answer == good_answer[k]:
        entire_rate[k] = similarity
    else:
        actual[k] = answer
        expected[k] = good_answer[k]
        bad_similarity[k] = similarity
        
        
print("Key : " + str(len(key_rate)))
print("Entire : " + str(len(entire_rate)))

average_key = sum(key_rate.values())/len(key_rate)
average_entire =  sum(entire_rate.values())/len(entire_rate)

print("Average key: " + str(average_key))
print("Average entire: " + str(average_entire))

for k, v in actual.items():
    print("Input: " + str(entire[k]))
    print("Expected output: " + str(expected[k]))
    print("Actual output: " + str(actual[k]))
    print("Similarity score " + str(bad_similarity[k]))

