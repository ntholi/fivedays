'use client';
import { experimental_useFormStatus as useFormStatus } from 'react-dom';
import { classroom_v1 } from 'googleapis';
import { createWork } from './actions';
import {
  TextInput,
  Button,
  Stack,
  Textarea,
  Grid,
  GridCol,
  Paper,
  Text,
} from '@mantine/core';

type Props = {
  course: classroom_v1.Schema$Course;
};

export default function CourseWorkForm({ course }: Props) {
  const handleSubmit = async (formData: FormData) => {
    console.log(`submitting ${course.id}`);
    if (course.id) {
      const res = await createWork({
        courseId: course.id,
        title: formData.get('title') as string,
        description: formData.get('description') as string,
      });
    }
  };

  return (
    <form action={handleSubmit}>
      <Grid mt='xl'>
        <GridCol span={{ base: 12, md: 8 }}>
          <Paper withBorder p={'md'}>
            <Stack>
              <TextInput required label='Title' name='title' />
              <Textarea
                label='Description'
                description='Optional Description'
                name='description'
                rows={8}
              />
            </Stack>
          </Paper>
        </GridCol>
        <GridCol span={{ base: 12, md: 4 }}>
          <Paper withBorder p={'md'}>
            <SubmitButton />
            <Text c='dimmed' mt={'xl'} size='xs'>
              I&lsquo;ll put more things here
            </Text>
          </Paper>
        </GridCol>
      </Grid>
    </form>
  );
}

const SubmitButton = () => {
  const { pending } = useFormStatus();
  return (
    <Button type='submit' loading={pending}>
      Submit
    </Button>
  );
};
