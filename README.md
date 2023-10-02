# FiveDays

FiveDays is a NextJS app that uses Artificial Intelligence to assist lecturers in creating and grading assessments. This project is built with [Next.js](https://nextjs.org/), [TypeScript](https://www.typescriptlang.org/), [Mantine](https://mantine.dev/), and the [OpenAI API](https://openai.com/).

![Project Banner](assets/banner.png) <!-- Replace with your project banner -->

## Features

- AI-powered creation of assessments based on a provided course outline.
- Automated grading of students' work using Artificial Intelligence.
- Integration with Google Classroom to fetch student's work and publish their grades.

## Getting Started

### Prerequisites

- Node.js
- npm
- An OpenAI API key
- Google Classroom API credentials

### Installation

1. Clone this repository:

```bash
git clone https://github.com/ntholi/fivedays.git
```

2. Install dependencies:

```bash
cd fivedays
npm install
```

3. Add your OpenAI API key and Google Classroom API credentials to a .env file:

```bash
echo "OPENAI_API_KEY=your-api-key" >> .env
echo "GOOGLE_CLASSROOM_CREDENTIALS=your-credentials" >> .env
```

4. Configure NextAuth.js. The GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET and JWT_SECRET environment variables are used to configure Google OAuth. You need to go to The [NextAuth.js Google OAuth documentation](https://next-auth.js.org/providers/google) to get your credentials. For more details see [the NextAuth.js documentation](https://next-auth.js.org/configuration/providers) for more information.

```bash
echo "GOOGLE_CLIENT_ID=your-client-id" >> .env
echo "GOOGLE_CLIENT_SECRET=your-client-secret" >> .env
echo "JWT_SECRET=your-jwt-secret" >> .env
```

5. To generate a JWT_SECRET use the [NextAuth.js secret generator](https://next-auth.js.org/configuration/options#secret). For more details see [the NextAuth.js documentation](https://next-auth.js.org/configuration/options#secret) for more information. You can also use a different secret for each provider by using the secret option in the provider configuration. Or run each of the following commands to generate a secret:

```bash
node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"
```

```bash
openssl rand -hex 32
```

6. Run the development server:

```bash
npm run dev
```

## Usage

_(this will be updated as the project progresses)_

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Acknowledgements

- [OpenAI](https://openai.com/) for their amazing API.
- [TypeScript](https://www.typescriptlang.org/) for making JavaScript better.
- [Next.js](https://nextjs.org/) for their amazing framework.
- [NextAuth.js](https://next-auth.js.org/) for their amazing authentication library.
- [Mantine](https://mantine.dev/) for their awesome React components.
- [Google Classroom](https://classroom.google.com/) for making learning easier.
- [Google Developers](https://developers.google.com/) for their amazing APIs.
- [Unsplash](https://unsplash.com/) for their amazing images.
