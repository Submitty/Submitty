#include<iostream>
using namespace std;
class node{
public:
string dog_breed;
float weight;
int age;
int code;
node *next;
};

class linked_list{
node *first;
public:
void create_ll(string breed[], float weight[], int age[], int code[], int size);
void display();
node *search(int code);
};

void linked_list::create_ll(string breed[], float weight[], int age[], int code[], int size)
{
   int i;
   node *t;
   node *last;
   first=NULL;
   first=new node;
   first->dog_breed=breed[0];
   first->age=age[0];
   first->weight=weight[0];
   first->code=code[0];
   first->next=NULL;
   last=first;

   for(i=1;i<size;i++)
   {
	t=new node;
	t->age=age[i];
	t->dog_breed=breed[i];
	t->weight=weight[i];
	t->code=code[i];
	last->next=t;
	t->next=NULL;
	last=t;
   }
}

void linked_list::display()
{
	int i=1;
	node *p=first;
	while(p->next!=NULL)
	{
		cout<<" "<<endl;
		cout<<i<<p->dog_breed<<endl;
		i++;
		p=p->next;
	}
}
node *linked_list::search(int code)
{
	cout<<"searching..."<<endl;
	node *p=first;
	while(p->next!=NULL)
	{
		if(p->code==code)
		{
            return p;
		}
		p=p->next;
	}
}


class Analysis:public linked_list{
linked_list l1;
int code;
public:
Analysis(int a);
void select_a_breed();
void Analyse(int code);
void under_weigh(int code);
};

Analysis::Analysis(int a)
{
	string name[]={"labrador", "german_shepard", "Rott wiler", "Golden retriver", "Pug", "Pomerian", "Huskey", "Doberman", "Boxer","Bull dog","abc"};
	int age[]={3,3,3,3,3,3,3,3,3,3,0};
	float weight[]={30,30,31,32,12,6,30,46,32,25,0};
	int code[]={1,2,3,4,5,6,7,8,9,10};
	l1.create_ll(name,weight,age,code,11);
}
void Analysis::select_a_breed()
{   
	cout<<"Displaying all the dog breeds :"<<endl;
	l1.display();
	cout<<" "<<endl;
	cout<<"Select a breed form the above list :"<<endl;
	int code;
	cin>>code;
	Analyse(code);
}

void Analysis::Analyse(int code)
{
	node *temp=l1.search(code);
    string name=temp->dog_breed;
	int age=temp->age;
	float weight=temp->weight;
    
	cout<<"Enter your dogs age :"<<endl;
	int dage;
	cin>>dage;
	cout<<"Enter your dogs weight :"<<endl;
	float dweight;
	cin>>dweight;

	float ratio1 = age/weight;
	float ratio2 = dage/dweight;
	int dif = ratio1/ratio2;
	if(ratio2>ratio1)
	{
      under_weigh(code);
	} 
    if(ratio2>0.06 && ratio2<=ratio1)
	{
       cout<<"Your Dog is perfectly healthy"<<endl;
	}
	if(ratio2<ratio1)
	{
      cout<<"Your dog is over weigiing for its age"<<endl;
	  cout<<" "<<endl;
	  cout<<" "<<endl<<"Here are some health tips for the dog"<<endl;
      cout<<" "<<endl<<"Just like humans, exercise is crucial when it comes to helping your overweight dog get healthy. Increasing your dog’s activity helps burn off energy (and calories consumed)."<<endl;
	  cout<<" "<<endl<<"If your dog begs, don’t assume that they’re hungry. Trust your instincts and keep track of when the last mealtime was."<<endl;
	  cout<<" "<<endl<<"Once your vet has designed a weight-management plan, you should have a clear idea of how much your dog should eat at each meal."<<endl;
	  cout<<" "<<endl<<"Customize Your Dog’s Diet"<<endl;
	}

}

void Analysis::under_weigh(int code)
{
   switch(code){
	case 1:cout<<"1.Your Dog is under weighing"<<endl<<"Health tips for a labrodor."<<endl;
	cout<<"2.A dog might stop eating or eat less of a current diet for texture, flavor or other reasons."<<endl;
	cout<<" "<<endl<<"3.A dog's environment might change, with a pet owner's new baby, a newly adopted animal, a new home, or an owner who isn't around because of work reasons. This can cause changes in feeding or eating patterns."<<endl;
	cout<<" "<<endl<<"4.Diseases and other medical issues are a prime culprit for sudden weight loss. Inflammatory bowel disease and kidney disease are possibilities"<<endl;
    break;

	case 2:cout<<"Diving deeper, the role of nutrition cannot be overstated. Is your German Shepherd's diet well-rounded and nutrient-rich? If not, it could lead to malnourishment, a key cause of thinness."<<endl;
	cout<<" "<<endl<<"Increasing meal frequency involves splitting your German Shepherd's daily food intake into smaller, more frequent meals. This method allows for better digestion and absorption of"<<endl;
	cout<<"Upgrading your German Shepherd's diet means choosing high-quality, nutrient-rich foods. These foods should be high in protein and contain the right balance of carbohydrates and fats"<<endl;
    break;

	case 3:cout<<"Giving your dog healthy snacks can increase their appetite and make them take more calories and nutrition. You can try snacks like beef jerky, chicken bone and doggie treats available at most stores."<<endl;
	cout<<" "<<endl;
	cout<<"An easy way to put some weight on your dog is by having the dog eat something dubbed satin balls. Invented in 1996, the satin ball is a ball of meat, eggs, oatmeal and other highly nutritious ingredients."<<endl;
    
	case 4:cout<<"Your Golden retriver is under weight."<<endl<<"Here are some health tips"<<endl;
	cout<<" "<<endl<<"Get a high-protein, high-fat dog food — dry, canned, frozen and/or freeze-dried — and give your dog three meals a day, weighing her every two weeks to make sure she is gaining weight. "<<endl;
	cout<<"Your dog might also have a digestive issue that can be helped with a couple of teaspoons of chopped canned unsweetened pineapple in each meal as a source of digestive enzymes.  "<<endl;

	case 5:cout<<"Your Pug is under weighing"<<endl<<"Here are some health tips"<<endl;
	cout<<"If you can see your Pug's ribs protruding through their coat when standing in a normal gait, then your Pug is underweight."<<endl;
	cout<<"It's not uncommon for canine dental problems to result in weight loss. If your Pug is experiencing pain in their teeth, mouth, or gums (eg. from an abscess) then eating will become uncomfortable for them. "<<endl;
	cout<<"If your Pug is taking medications for an ailment or illness then this can impact their weight. "<<endl;

	case 6:cout<<"Your pomerinian is under weight"<<endl;
	cout<<" "<<endl<<"Providing an excellent diet at all times during your Pomeranian's life is vital for maintaining good health, however it is never so crucial as during the first year when the puppy needs to be gaining weight. Lack of proper growth or trouble maintaining may be due to insufficient nutrition. "<<endl;
	cout<<" "<<endl<<"While this toy breed dog does not require a lot of calories, there can be instances of misunderstanding in regard to food consumption."<<endl;

	case 7:cout<<"Most Husky owners have an equally active lifestyle as their pets. But there is such a thing as too much exercise, and if your dog is burning off more energy than they consume they will start to lose weight."<<endl;
	cout<<" "<<endl<<"These can be simple infections, parasites, eating something that doesn’t agree with them, or something more serious such as liver or kidney disease, hormonal conditions, or cancer."<<endl;
	cout<<" "<<endl<<"Huskies are highly intelligent and sensitive dogs. Any changes in their routine or environment can cause them stress."<<endl;

	case 8:cout<<"As Dobies have a reputation for needing a lot of exercise, it’s easy to get carried away with it. If you take your Dobie out for very long runs for several miles or go out hiking for the entire day it could actually be too much for him."<<endl;
	cout<<" "<<endl<<"On the topic of food still, it’s incredibly important to ensure your Doberman is consuming a high protein, medium-high fat, and low carbohydrate diet."<<endl;

	case 9:cout<<"Reevaluate what you are feeding your Boxer. While a Boxer should be fed a super high quality food at all times, when he/she is underweight and trying to fill out into his adult frame"<<endl;
	cout<<" "<<endl<<" Since this involves routinely giving a mix of calorie dense food that is higher in fat than recommended for canines, run this by your vet first."<<endl;
	cout<<" "<<endl<<"Allow your Boxer dog opportunities to stretch and grow his/her muscles."<<endl;
	
	case 10:cout<<" The most obvious way to help your dog gain weight is to feed him more food."<<endl;
	cout<<" "<<endl<<"0-50 calories per pound of body weight each day. An overweight or obese dog should be fed 30-40 % less than the above range"<<endl;
	cout<<" "<<endl<<"probiotic supplement for digestive health and fish oil for joint health. She also suggests adding extra Vitamin D if your dog spends most of his time indoors or has little exposure to sunlight"<<endl;

	default:cout<<"ERROR"<<endl;
   }
    
   
}
int main()
{
	cout<<"___WELCOME___"<<endl;
	Analysis a1(0);
	a1.select_a_breed();
	cout<<"Thank you"<<endl;
	cout<<"Do follow the tips to give your dog a healthy life"<<endl;
}
